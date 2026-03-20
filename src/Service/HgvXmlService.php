<?php

namespace App\Service;

use App\Dto\HgvRecord;

/**
 * Service that queries the BaseX HGV and DDB XML databases and returns HgvRecord DTOs.
 *
 * All XQuery strings are constructed here.  User values are escaped before
 * interpolation to prevent XQuery injection (single-quote doubling in XQuery
 * string literals).
 */
class HgvXmlService
{
    private BaseXClient $client;

    // ── Field → XQuery expression (references bound $let variables in the FLWOR) ──

    /**
     * Maps PHP search-field names to the XQuery expression whose string value
     * is used for comparison.  These expressions depend on the let-bindings
     * in the base FLWOR (see buildSearchXQuery / buildCountXQuery).
     */
    private const FIELD_EXPR = [
        'publikation'       => "string(\$pub/tei:title[@type='abbreviated'])",
        'band'              => "string(\$pub/tei:biblScope[@type='volume'])",
        'nummer'            => "string(\$pub/tei:biblScope[@type='numbers'])",
        'tmNr'              => "string(\$doc//tei:idno[@type='TM'])",
        'ort'               => "\$place",
        'originaltitel'     => "\$title",
        'material'          => "\$material",
        'datierungIi'       => "\$dating",
        'anderePublikation' => "\$otherPubs",
        'inhalt'            => "\$keywords",
        'abbildung'         => "\$illustrations",
        'bemerkungen'       => "\$commentary",
        'uebersetzungen'    => "\$translationsPlain",
        'url'               => "string-join(\$doc//tei:figure/tei:graphic/@url, ' ')",
        'chronMinimum'      => "\$notBefore",
        'chronMaximum'      => "\$notAfter",
        'chronGlobal'       => "\$notBefore",   // approximate: used as sortYear integer
        'jahr'              => "\$notBefore",
        'jh'                => "\$notBefore",
    ];

    /** Fields that hold ISO-year strings and should be compared as integers. */
    private const NUMERIC_FIELDS = [
        'chronMinimum', 'chronMaximum', 'chronGlobal', 'jahr', 'jh', 'jhIi',
        'jahrIi', 'monat', 'monatIi', 'tag', 'tagIi',
    ];

    // ── Sort key → XQuery order-by expression ────────────────────────────────────

    private const SORT_EXPR = [
        'chronGlobal'       => "\$sortYear",
        'datierungIi'       => "\$sortYear",
        'monat'             => "\$sortYear",
        'tag'               => "\$sortYear",
        'chronMinimum'      => "\$sortYear",
        'chronMaximum'      => "\$sortYear",
        'publikation'       => "\$pubAbbr, \$pubVol, \$pubNr",
        'publikationLang'   => "\$pubAbbr, \$pubVol, \$pubNr",
        'ort'               => "\$place",
        'originaltitel'     => "\$title",
        'originaltitelHtml' => "\$title",
        'material'          => "\$material",
        'tmNr'              => "\$sortTm",
        'inhalt'            => "\$keywords",
    ];

    public function __construct(BaseXClient $client)
    {
        $this->client = $client;
    }

    // ── Public API ────────────────────────────────────────────────────────────────

    /**
     * Returns total and per-criteria counts (no data rows) for the multiple() view.
     *
     * @return array{total: int, filtered: int}
     */
    public function countRecords(array $criteria, string $operator): array
    {
        $where = $this->buildWhereClause($criteria, $operator);
        $xquery = $this->buildCountXQuery($where);

        try {
            $result = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return ['total' => 0, 'filtered' => 0];
        }

        return [
            'total'    => (int)($result['total']    ?? 0),
            'filtered' => (int)($result['filtered'] ?? 0),
        ];
    }

    /**
     * Search, filter, sort and paginate — used by the DataTables AJAX endpoint.
     *
     * @param  array{criteria: array, operator: string, skip: int, max: int} $search
     * @param  array<int,array{key: string, direction: string}>              $sort
     * @return array{total: int, filtered: int, data: HgvRecord[]}
     */
    public function searchPaginated(array $search, array $sort): array
    {
        $offset   = max(0, (int)($search['skip'] ?? 0)) + 1;  // XQuery is 1-based
        $limit    = max(0, (int)($search['max']  ?? 25));
        $criteria = $search['criteria'] ?? [];
        $operator = ($search['operator'] ?? 'and') === 'or' ? 'or' : 'and';

        $where  = $this->buildWhereClause($criteria, $operator);
        $order  = $this->buildOrderClause($sort);
        $xquery = $this->buildSearchXQuery($where, $order, $offset, $limit);

        try {
            $result = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return ['total' => 0, 'filtered' => 0, 'data' => []];
        }

        $records = [];
        foreach ($result['data'] ?? [] as $row) {
            $records[] = $this->rowToRecord($row);
        }

        return [
            'total'    => (int)($result['total']    ?? 0),
            'filtered' => (int)($result['filtered'] ?? 0),
            'data'     => $records,
        ];
    }

    /**
     * Load a single full-detail record by HGV filename/ID (for browse/single view).
     * Also fetches the DDB transcription text from the ddb database.
     */
    public function getFullRecord(string $id): ?HgvRecord
    {
        $safeId = $this->escXQ($id);
        $xquery = $this->buildFullRecordXQuery($safeId);

        try {
            $data = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($data) || !isset($data['id'])) {
            return null;
        }

        // Fetch DDB transcription
        if (!empty($data['ddb'])) {
            $data['ddbText'] = $this->fetchDdbText($data['ddb']);
        }

        return $this->fullRowToRecord($data);
    }

    /**
     * Find records by TM number (shortcut /tm/{id}).
     * @return HgvRecord[]
     */
    public function findByTm(string $tm): array
    {
        return $this->findByIdno('TM', $tm);
    }

    /**
     * Find a record by full HGV filename/ID, e.g. "8981a" (shortcut /hgv/{id}).
     */
    public function findByFilename(string $id): ?HgvRecord
    {
        $records = $this->findByIdno('filename', $id);
        return $records[0] ?? null;
    }

    /**
     * Find records matching a ddb-hybrid identifier (shortcut /ddb/{id}).
     * @return HgvRecord[]
     */
    public function findByDdbHybrid(string $hybrid): array
    {
        return $this->findByIdno('ddb-hybrid', $hybrid);
    }

    /**
     * Find records matching explicit publication parts (used by /ddb/; shortcut with 6 parts).
     * @return HgvRecord[]
     */
    public function findByPublicationParts(string $pub, string $band, string $zusBand, string $nummer, string $seite, string $zusaetzlich): array
    {
        // Build ddb-hybrid from parts: "pub;band;nummer" or similar
        // Fallback: search by principal edition fields
        $safePub  = $this->escXQ($pub);
        $safeVol  = $this->escXQ($band);
        $safeNr   = $this->escXQ($nummer);

        $conditions = ["string(\$pub/tei:title[@type='abbreviated']) = '$safePub'"];
        if ($safeVol !== '') {
            $conditions[] = "string(\$pub/tei:biblScope[@type='volume']) = '$safeVol'";
        }
        if ($safeNr !== '') {
            $conditions[] = "string(\$pub/tei:biblScope[@type='numbers']) = '$safeNr'";
        }
        $whereInner = implode(' and ', $conditions);

        $xquery = <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";
array {
  for \$doc in db:get('hgv')//tei:TEI
  let \$pub := \$doc//tei:bibl[@type='publication'][@subtype='principal']
  where $whereInner
  return map {
    "id":  string(\$doc//tei:idno[@type='filename']),
    "tm":  string(\$doc//tei:idno[@type='TM']),
    "ddb": string(\$doc//tei:idno[@type='ddb-hybrid']),
    "publ":    concat(string(\$pub/tei:title[@type='abbreviated']),
               if (string(\$pub/tei:biblScope[@type='volume']) != '') then concat(' ', string(\$pub/tei:biblScope[@type='volume'])) else '',
               if (string(\$pub/tei:biblScope[@type='numbers']) != '') then concat(' ', string(\$pub/tei:biblScope[@type='numbers'])) else ''),
    "pubAbbr": string(\$pub/tei:title[@type='abbreviated']),
    "pubVol":  string(\$pub/tei:biblScope[@type='volume']),
    "pubNr":   string(\$pub/tei:biblScope[@type='numbers']),
    "dating":  normalize-space(string(\$doc//tei:origDate)),
    "place":   string(\$doc//tei:origPlace),
    "title":   string(\$doc//tei:titleStmt/tei:title),
    "material": string(\$doc//tei:material)
  }
  order by string(\$doc//tei:idno[@type='filename']) ascending
}
XQ;

        try {
            $rows = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return [];
        }

        return array_map([$this, 'rowToRecord'], is_array($rows) ? $rows : []);
    }

    // ── XQuery builders ───────────────────────────────────────────────────────────

    /**
     * The common FLWOR let-bindings used by both search and count queries.
     * Every field referenced in FIELD_EXPR or SORT_EXPR must be bound here.
     */
    private function commonLetBindings(): string
    {
        return <<<'XQ'
  let $pub          := ($doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
  let $pubAbbr      := string($pub/tei:title[@type='abbreviated'])
  let $pubVol       := string($pub/tei:biblScope[@type='volume'])
  let $pubNr        := string($pub/tei:biblScope[@type='numbers'])
  let $origDate     := ($doc//tei:history/tei:origin/tei:origDate)[1]
  let $notBefore    := string(($origDate/@notBefore, $origDate/@when)[1])
  let $notAfter     := string(($origDate/@notAfter,  $origDate/@when)[1])
  let $place        := string(($doc//tei:origPlace)[1])
  let $title        := string(($doc//tei:titleStmt/tei:title)[1])
  let $material     := string(($doc//tei:material)[1])
  let $keywords     := string-join($doc//tei:keywords[@scheme='hgv']/tei:term/text(), '; ')
  let $otherPubs    := string-join($doc//tei:bibl[@type='publication'][@subtype='other']/text(), '; ')
  let $commentary   := string-join($doc//tei:div[@type='commentary'][@subtype='general']/tei:p/text(), ' ')
  let $illustrations := string-join($doc//tei:bibl[@type='illustration']/text(), '; ')
  let $dating       := normalize-space(string($origDate))
  let $translationsPlain := string-join(
    $doc//tei:div[@type='bibliography'][@subtype='translations']//tei:bibl[@type='translations']/text(), '; ')
  let $sortYear     := if ($notBefore != '' and $notBefore castable as xs:integer)
                       then xs:integer($notBefore)
                       else if ($notAfter != '' and $notAfter castable as xs:integer)
                       then xs:integer($notAfter)
                       else 9999
  let $sortTm       := if (string(($doc//tei:idno[@type='TM'])[1]) castable as xs:integer)
                       then xs:integer(string(($doc//tei:idno[@type='TM'])[1]))
                       else 0
XQ;
    }

    /**
     * Build count-only XQuery that returns {"total": N, "filtered": M}.
     */
    private function buildCountXQuery(string $where): string
    {
        $bindings = $this->commonLetBindings();
        $whereStr = $where ? "\n  $where" : '';

        return <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";

let \$total := count(db:get('hgv')//tei:TEI)
let \$filtered :=
  count(
    for \$doc in db:get('hgv')//tei:TEI
$bindings$whereStr
    return 1
  )
return map { "total": \$total, "filtered": \$filtered }
XQ;
    }

    /**
     * Build full search-and-paginate XQuery returning
     * {"total": N, "filtered": M, "data": [...]}.
     */
    private function buildSearchXQuery(string $where, string $order, int $offset, int $limit): string
    {
        $bindings = $this->commonLetBindings();
        $whereStr = $where ? "\n  $where" : '';
        $orderStr = $order ? "\n  $order" : '';

        return <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";

let \$total := count(db:get('hgv')//tei:TEI)
let \$results :=
  for \$doc in db:get('hgv')//tei:TEI
$bindings$whereStr$orderStr
  return map {
    "id":       string((\$doc//tei:idno[@type='filename'])[1]),
    "tm":       string((\$doc//tei:idno[@type='TM'])[1]),
    "ddb":      string((\$doc//tei:idno[@type='ddb-hybrid'])[1]),
    "publ":     concat(\$pubAbbr,
                  if (\$pubVol != '') then concat(' ', \$pubVol) else '',
                  if (\$pubNr  != '') then concat(' ', \$pubNr)  else ''),
    "pubAbbr":  \$pubAbbr,
    "pubVol":   \$pubVol,
    "pubNr":    \$pubNr,
    "dating":   \$dating,
    "notBefore": \$notBefore,
    "notAfter":  \$notAfter,
    "place":    \$place,
    "title":    \$title,
    "material": \$material,
    "keywords": \$keywords,
    "otherPub": \$otherPubs,
    "commentary": \$commentary,
    "illustrations": \$illustrations
  }
return map {
  "total":    \$total,
  "filtered": count(\$results),
  "data":     array { subsequence(\$results, $offset, $limit) }
}
XQ;
    }

    /**
     * Build a full-detail single-record XQuery.
     * $safeId must already be XQuery-escaped.
     */
    private function buildFullRecordXQuery(string $safeId): string
    {
        return <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";

let \$doc := (db:get('hgv')//tei:TEI[
  tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='filename'] = '$safeId'
])[1]
return
  if (\$doc)
  then
    let \$pub      := (\$doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
    let \$pubAbbr  := string(\$pub/tei:title[@type='abbreviated'])
    let \$pubVol   := string(\$pub/tei:biblScope[@type='volume'])
    let \$pubNr    := string(\$pub/tei:biblScope[@type='numbers'])
    let \$ddb      := string((\$doc//tei:idno[@type='ddb-hybrid'])[1])
    let \$ddbParts := tokenize(\$ddb, ';')
    let \$origDate := (\$doc//tei:history/tei:origin/tei:origDate)[1]
    let \$notBefore := string((\$origDate/@notBefore, \$origDate/@when)[1])
    let \$notAfter  := string((\$origDate/@notAfter,  \$origDate/@when)[1])
    let \$blEntries :=
      for \$bl in \$doc//tei:bibl[@type='BL']
      let \$vol  := string(\$bl/tei:biblScope[@type='volume'])
      let \$pgs  := string(\$bl/tei:biblScope[@type='pages'])
      where \$vol != '' or \$pgs != ''
      return concat('BL ', \$vol, ', S. ', \$pgs)
    let \$blOnline := exists(\$doc//tei:bibl[@type='BL-online'])
    let \$translations := string-join(
      for \$lb in \$doc//tei:div[@type='bibliography'][@subtype='translations']/tei:listBibl
      let \$head := string(\$lb/tei:head)
      let \$bibs := string-join(\$lb/tei:bibl[@type='translations']/text(), '; ')
      where \$bibs != ''
      return concat(\$head, ' ', \$bibs),
      ' ')
    let \$mentionedDates :=
      for \$item in \$doc//tei:div[@type='commentary'][@subtype='mentionedDates']//tei:list/tei:item
      return map {
        "zeile":     string(\$item/tei:ref),
        "datierung": normalize-space(string(\$item/tei:date[@type='mentioned']))
      }
    let \$figures :=
      for \$g in \$doc//tei:figure/tei:graphic/@url
      return string(\$g)
    let \$provenance := string-join(
      \$doc//tei:provenance[@type='located']//tei:placeName[@type='ancient']/text(), ' – ')
    return map {
      "id":         string((\$doc//tei:idno[@type='filename'])[1]),
      "tm":         string((\$doc//tei:idno[@type='TM'])[1]),
      "ddb":        \$ddb,
      "ddbSer":     string(\$ddbParts[1]),
      "ddbVol":     string(\$ddbParts[2]),
      "ddbDoc":     string(\$ddbParts[3]),
      "publ":       concat(\$pubAbbr,
                      if (\$pubVol != '') then concat(' ', \$pubVol) else '',
                      if (\$pubNr  != '') then concat(' ', \$pubNr)  else ''),
      "pubAbbr":    \$pubAbbr,
      "pubVol":     \$pubVol,
      "pubNr":      \$pubNr,
      "dating":     normalize-space(string(\$origDate)),
      "notBefore":  \$notBefore,
      "notAfter":   \$notAfter,
      "place":      string((\$doc//tei:origPlace)[1]),
      "title":      string((\$doc//tei:titleStmt/tei:title)[1]),
      "material":   string((\$doc//tei:material)[1]),
      "invNo":      string((\$doc//tei:msIdentifier/tei:idno[@type='invNo'])[1]),
      "settlement": string((\$doc//tei:msIdentifier/tei:placeName/tei:settlement)[1]),
      "keywords":   string-join(\$doc//tei:keywords[@scheme='hgv']/tei:term/text(), '; '),
      "otherPub":   string-join(\$doc//tei:bibl[@type='publication'][@subtype='other']/text(), '; '),
      "commentary": string-join(\$doc//tei:div[@type='commentary'][@subtype='general']/tei:p/text(), ' '),
      "bl":         string-join(\$blEntries, '; '),
      "blOnline":   \$blOnline,
      "translations": \$translations,
      "illustrations": string-join(\$doc//tei:bibl[@type='illustration']/text(), '; '),
      "figureUrls": array { \$figures },
      "mentionedDates": array { \$mentionedDates },
      "erwaehnteDaten": string((\$doc//tei:div[@type='commentary'][@subtype='mentionedDates']/tei:note[@type='original'])[1]),
      "provenance": \$provenance,
      "pictureLinks": array {
        for \$g in \$doc//tei:figure/tei:graphic
        return map { "url": string(\$g/@url), "institution": "" }
      }
    }
  else map {}
XQ;
    }

    // ── WHERE clause builder ──────────────────────────────────────────────────────

    private function buildWhereClause(array $criteria, string $operator): string
    {
        if (empty($criteria)) {
            return '';
        }

        $conditions = [];
        foreach ($criteria as $field => $criterion) {
            $cond = $this->buildCondition($field, $criterion['operator'], $criterion['value']);
            if ($cond !== null) {
                $conditions[] = $cond;
            }
        }

        if (empty($conditions)) {
            return '';
        }

        $xqOp = $operator === 'or' ? ' or ' : ' and ';
        return 'where ' . implode($xqOp, $conditions);
    }

    /**
     * Build a single XQuery predicate for one criterion.
     * Returns null when the field is unknown.
     */
    private function buildCondition(string $field, string $op, string $value): ?string
    {
        // Special wildcard operators
        if ($value === '*') {
            // Field is non-empty
            $expr = self::FIELD_EXPR[$field] ?? null;
            if (!$expr) return null;
            return "($expr != '' and $expr != ())";
        }
        if ($value === '=') {
            // Field is empty
            $expr = self::FIELD_EXPR[$field] ?? null;
            if (!$expr) return null;
            return "($expr = '' or not($expr))";
        }

        // Date range: "100...200"
        if (preg_match('/^(-?\d+)\.{2,}(-?\d+)$/', $value, $m)) {
            return $this->buildDateRangeCondition($field, (int)$m[1], (int)$m[2]);
        }

        // Splittersuche (operator 'sp'): all words must appear
        if ($op === 'sp') {
            $expr = self::FIELD_EXPR[$field] ?? null;
            if (!$expr) return null;
            $parts = [];
            foreach (preg_split('/\s+/', trim($value)) as $word) {
                if ($word === '') continue;
                $safeWord = $this->escXQ($word);
                $parts[] = "contains(lower-case($expr), lower-case('$safeWord'))";
            }
            return empty($parts) ? null : '(' . implode(' and ', $parts) . ')';
        }

        $expr = self::FIELD_EXPR[$field] ?? null;
        if (!$expr) {
            return null; // unknown field — skip silently
        }

        $safeVal = $this->escXQ($value);
        $isNumeric = in_array($field, self::NUMERIC_FIELDS, true);

        switch ($op) {
            case 'cn': return "contains(lower-case($expr), lower-case('$safeVal'))";
            case 'bw': return "starts-with(lower-case($expr), lower-case('$safeVal'))";
            case 'ew': return "ends-with(lower-case($expr), lower-case('$safeVal'))";
            case 'eq':
                if ($isNumeric && is_numeric($value)) {
                    return "($expr castable as xs:integer and xs:integer($expr) = $safeVal)";
                }
                return "lower-case($expr) = lower-case('$safeVal')";
            case 'neq':
                if ($isNumeric && is_numeric($value)) {
                    return "($expr castable as xs:integer and xs:integer($expr) != $safeVal)";
                }
                return "lower-case($expr) != lower-case('$safeVal')";
            case 'lt':
                if ($isNumeric && is_numeric($value)) {
                    return "($expr castable as xs:integer and xs:integer($expr) < $safeVal)";
                }
                return "$expr < '$safeVal'";
            case 'lte':
                if ($isNumeric && is_numeric($value)) {
                    return "($expr castable as xs:integer and xs:integer($expr) <= $safeVal)";
                }
                return "$expr <= '$safeVal'";
            case 'gt':
                if ($isNumeric && is_numeric($value)) {
                    return "($expr castable as xs:integer and xs:integer($expr) > $safeVal)";
                }
                return "$expr > '$safeVal'";
            case 'gte':
                if ($isNumeric && is_numeric($value)) {
                    return "($expr castable as xs:integer and xs:integer($expr) >= $safeVal)";
                }
                return "$expr >= '$safeVal'";
        }

        return null;
    }

    /**
     * Build numeric year-range condition for date fields.
     */
    private function buildDateRangeCondition(string $field, int $from, int $to): ?string
    {
        // Map field to appropriate XQuery year expression
        $expr = match ($field) {
            'chronMinimum', 'jahr' => "\$notBefore",
            'chronMaximum'         => "\$notAfter",
            'chronGlobal'          => "\$notBefore",
            default                => null,
        };
        if (!$expr) return null;
        return "($expr castable as xs:integer and xs:integer($expr) >= $from and xs:integer($expr) <= $to)";
    }

    // ── ORDER BY builder ──────────────────────────────────────────────────────────

    private function buildOrderClause(array $sort): string
    {
        if (empty($sort)) {
            // Default: ascending by date
            return 'order by $sortYear ascending';
        }

        $parts = [];
        foreach ($sort as $item) {
            $key  = $item['key']       ?? '';
            $dir  = ($item['direction'] ?? 'ascend') === 'descend' ? 'descending' : 'ascending';
            $expr = self::SORT_EXPR[$key] ?? null;
            if (!$expr) continue;
            foreach (explode(',', $expr) as $e) {
                $parts[] = trim($e) . ' ' . $dir;
            }
        }

        if (empty($parts)) {
            return 'order by $sortYear ascending';
        }

        return 'order by ' . implode(', ', $parts);
    }

    // ── findByIdno helper ─────────────────────────────────────────────────────────

    /**
     * Generic lookup by any @type on tei:idno in publicationStmt.
     * Returns list of lightweight HgvRecord objects.
     *
     * @return HgvRecord[]
     */
    private function findByIdno(string $idnoType, string $value): array
    {
        $safeType  = $this->escXQ($idnoType);
        $safeValue = $this->escXQ($value);

        $xquery = <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";
array {
  for \$doc in db:get('hgv')//tei:TEI[
    tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='$safeType'] = '$safeValue'
  ]
  let \$pub      := (\$doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
  let \$pubAbbr  := string(\$pub/tei:title[@type='abbreviated'])
  let \$pubVol   := string(\$pub/tei:biblScope[@type='volume'])
  let \$pubNr    := string(\$pub/tei:biblScope[@type='numbers'])
  let \$origDate := (\$doc//tei:history/tei:origin/tei:origDate)[1]
  order by string((\$doc//tei:idno[@type='filename'])[1]) ascending
  return map {
    "id":       string((\$doc//tei:idno[@type='filename'])[1]),
    "tm":       string((\$doc//tei:idno[@type='TM'])[1]),
    "ddb":      string((\$doc//tei:idno[@type='ddb-hybrid'])[1]),
    "publ":     concat(\$pubAbbr,
                  if (\$pubVol != '') then concat(' ', \$pubVol) else '',
                  if (\$pubNr  != '') then concat(' ', \$pubNr)  else ''),
    "pubAbbr":  \$pubAbbr,
    "pubVol":   \$pubVol,
    "pubNr":    \$pubNr,
    "dating":   normalize-space(string(\$origDate)),
    "notBefore": string((\$origDate/@notBefore, \$origDate/@when)[1]),
    "notAfter":  string((\$origDate/@notAfter,  \$origDate/@when)[1]),
    "place":    string((\$doc//tei:origPlace)[1]),
    "title":    string((\$doc//tei:titleStmt/tei:title)[1]),
    "material": string((\$doc//tei:material)[1]),
    "keywords": string-join(\$doc//tei:keywords[@scheme='hgv']/tei:term/text(), '; ')
  }
}
XQ;

        try {
            $rows = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return [];
        }

        // If a single-element JSON array is returned as an object, wrap it
        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        return array_map([$this, 'rowToRecord'], is_array($rows) ? $rows : []);
    }

    // ── DDB text retrieval ────────────────────────────────────────────────────────

    /**
     * Fetch the transcription text from the DDB database for a given ddb-hybrid id.
     * Returns a plain-text-as-HTML rendition with line numbers, or empty string.
     */
    private function fetchDdbText(string $ddbHybrid): string
    {
        $safeHybrid = $this->escXQ($ddbHybrid);

        $xquery = <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
let \$doc := (db:get('ddb')//tei:TEI[
  tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='ddb-hybrid'] = '$safeHybrid'
])[1]
return
  if (\$doc)
  then serialize((\$doc//tei:div[@type='edition']//tei:ab)[1])
  else ''
XQ;

        try {
            $raw = $this->client->xquery($xquery);
        } catch (\Throwable $e) {
            return '';
        }

        if (trim($raw) === '') {
            return '';
        }

        return $this->renderDdbXml($raw);
    }

    /**
     * Convert a serialized DDB <ab> XML fragment into a readable HTML rendition.
     * Preserves line numbers; strips EpiDoc markup; escapes remaining text.
     */
    private function renderDdbXml(string $xml): string
    {
        // <lb n="N"/> → line-number marker (keep on new line)
        $text = preg_replace_callback(
            '/<lb\s+n="(\d+)"[^>]*\/?>/',
            static fn($m) => "\n[{$m[1]}]\u{00A0}",
            $xml
        );
        // <choice><reg>X</reg><orig>Y</orig></choice> → prefer reg text
        $text = preg_replace('/<choice[^>]*>.*?<reg[^>]*>(.*?)<\/reg>.*?<\/choice>/s', '$1', $text);
        // Common EpiDoc elements: keep text content
        $text = preg_replace('/<\/?(?:ab|app|supplied|unclear|expan|abbr|ex|add|del|orig|reg|corr|sic|hi|handShift|milestone|space)[^>]*>/i', '', $text);
        // <gap> → replacement marker
        $text = preg_replace('/<gap[^>]*\/?>/', '[– – –]', $text);
        // <num value="…">…</num> → keep inner text
        $text = preg_replace('/<num[^>]*>(.*?)<\/num>/s', '$1', $text);
        // Strip any remaining tags
        $text = strip_tags($text);
        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize spaces on each line
        $lines = array_map('trim', explode("\n", $text));
        $lines = array_filter($lines, static fn($l) => $l !== '');
        $text  = implode("\n", $lines);
        // Escape for HTML output and convert newlines to <br>
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    // ── Row → HgvRecord converters ────────────────────────────────────────────────

    /**
     * Convert a lightweight search-result row array into an HgvRecord.
     */
    private function rowToRecord(array $row): HgvRecord
    {
        // Parse ddb-hybrid into parts
        $ddb   = $row['ddb'] ?? '';
        $parts = explode(';', $ddb, 3);

        // Build pictureLinks from illustration text (URL-only rows have no institution)
        $pictureLinks = [];
        if (!empty($row['figureUrls']) && is_array($row['figureUrls'])) {
            foreach ($row['figureUrls'] as $url) {
                $pictureLinks[] = ['url' => (string)$url, 'institution' => ''];
            }
        }

        return new HgvRecord(array_merge($row, [
            'ddbSer'       => $parts[0] ?? '',
            'ddbVol'       => $parts[1] ?? '',
            'ddbDoc'       => $parts[2] ?? '',
            'pictureLinks' => $pictureLinks,
        ]));
    }

    /**
     * Convert a full-detail row (from buildFullRecordXQuery) into an HgvRecord.
     */
    private function fullRowToRecord(array $row): HgvRecord
    {
        // mentionedDates come back as array of maps
        $md = [];
        if (!empty($row['mentionedDates']) && is_array($row['mentionedDates'])) {
            foreach ($row['mentionedDates'] as $item) {
                $md[] = [
                    'zeile'     => (string)($item['zeile']     ?? ''),
                    'datierung' => (string)($item['datierung'] ?? ''),
                ];
            }
        }

        // pictureLinks come back from XQuery as array of maps
        $pl = [];
        if (!empty($row['pictureLinks']) && is_array($row['pictureLinks'])) {
            foreach ($row['pictureLinks'] as $item) {
                $url  = (string)($item['url'] ?? '');
                if ($url === '') continue;
                $pl[] = [
                    'url'         => $url,
                    'institution' => (string)($item['institution'] ?? ''),
                ];
            }
        }

        return new HgvRecord(array_merge($row, [
            'mentionedDates' => $md,
            'pictureLinks'   => $pl,
            'blOnline'       => !empty($row['blOnline']),
        ]));
    }

    // ── Utility ───────────────────────────────────────────────────────────────────

    /**
     * Escape a string for safe embedding in an XQuery single-quoted string literal.
     * Single quotes are doubled: O'Brien → O''Brien
     */
    private function escXQ(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
