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
        /* ids */
        'tm'                => "string(\$doc//tei:idno[@type='TM'])",
        'ddb'               => "string((\$doc//tei:idno[@type='ddb-hybrid'])[1])",
        'hgv'               => "string((\$doc//tei:idno[@type='filename'])[1])",
        /* inventory number */
        'settlement'        => "\$settlement",
        'collection'        => "\$collection",
        'invNo'             => "\$invNo",
        /* description */
        'title'             => "\$title",
        'material'          => "\$material",
        'keywords'          => "\$keywords",
        'commentary'        => "\$commentary",
        /* place */
        'place'             => "\$place",
        'provenance'        => "\$provenance",
        'provenancePlace'   => "\$provenancePlace",
        'provenanceNome'    => "\$provenanceNome",
        /* dating */
        'dating'            => "\$dating",
        'notBefore'         => "\$notBefore",
        'notAfter'          => "\$notAfter",
        'when'              => "\$when",
        'precision'         => "\$precision",
        'sortYear'          => "\$notBefore",   // approximate: used as sortYear integer
        'year'              => "\$notBefore",
        'century'           => "\$notBefore",
        'mentionedDatesText' => "\$mentionedDatesText",
        /* publication */
        'publication'       => "string(\$pub/tei:title[@type='abbreviated'])",
        'volume'            => "string(\$pub/tei:biblScope[@type='volume'])",
        'number'            => "string(\$pub/tei:biblScope[@type='numbers'])",
        'pubAbbr'           => "\$pubAbbr",
        'pubVol'            => "\$pubVol",
        'pubNr'             => "\$pubNr",
        /* images and bibliography */
        'otherPublications' => "\$otherPubs",
        'translations'      => "\$translationsPlain",
        'illustrations'     => "\$illustrations",
        'figureUrls'        => "\$figureUrls",
        'blOnline'          => "\$blOnline",
        'url'               => "string-join(\$doc//tei:figure/tei:graphic/@url, ' ')"
    ];

    /**
     * Multi-date fields: expressions reference $od (an individual tei:origDate
     * element) and are wrapped with "some $od in $origDates satisfies (...)"
     * in buildCondition().
     */
    private const MULTI_DATE_FIELD_EXPR = [
        'dating'    => "normalize-space(string(\$od))",
        'when'      => "string(\$od/@when)",
        'precision' => "string((\$od/@precision, \$od/@cert)[1])",
        'notBefore' => "string((\$od/@notBefore, \$od/@when)[1])",
        'notAfter'  => "string((\$od/@notAfter,  \$od/@when)[1])",
        'year'      => "string((\$od/@notBefore, \$od/@when)[1])",
        'century'   => "string((\$od/@notBefore, \$od/@when)[1])",
        'sortYear'  => "string((\$od/@notBefore, \$od/@when)[1])",
    ];

    /**
     * Multi-provenance fields: expressions reference $prov (an individual
     * tei:provenance element) and are wrapped with
     * "some $prov in $provenances satisfies (...)" in buildCondition().
     */
    private const MULTI_PROVENANCE_FIELD_EXPR = [
        'provenance'      => "string-join(\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ' ')",
        'provenancePlace' => "string-join(\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ' ')",
        'provenanceNome'  => "string(\$prov/tei:p/tei:placeName[@subtype='nome'])",
    ];

    /** Fields that hold ISO-year strings and should be compared as integers. */
    private const NUMERIC_FIELDS = [
        'notBefore', 'notAfter', 'sortYear', 'year', 'century',
    ];

    // ── Sort key → XQuery order-by expression ────────────────────────────────────

    private const SORT_EXPR = [
        /* ids */
        'tm'                 => "\$sortTm",
        'ddb'                => "\$sortDdbSer, \$sortDdbVol, \$sortDdbDoc",
        'hgv'                => "\$sortHgvNum, \$sortHgvSuffix",
        /* inventory number */
        'settlement'         => "\$settlement",
        'collection'         => "\$collection",
        'invNo'              => "\$invNo",
        /* description */
        'title'              => "\$title",
        'material'           => "\$material",
        'keywords'           => "\$keywords",
        'commentary'         => "\$commentary",
        /* place */
        'place'              => "\$place",
        'provenance'         => "\$provenance",
        'provenancePlace'    => "\$provenancePlace",
        'provenanceNome'     => "\$provenanceNome",
        /*dating (sorted by notBefore, then notAfter, then when) */
        'dating'             => "\$sortYear",
        'sortYear'           => "\$sortYear",
        'notBefore'          => "\$sortYear",
        'notAfter'           => "\$sortYear",
        'when'               => "\$when",
        'mentionedDatesText' => "\$mentionedDatesText",
        'precision'          => "\$precision",
        /* publication */
        'publication'        => "\$pubAbbr, \$sortPubVol, \$sortPubNr",
        'pubAbbr'            => "\$pubAbbr",
        'pubVol'             => "\$sortPubVol",
        'pubNr'              => "\$sortPubNr",
        /* images and bibliography */
        'otherPublications'  => "\$otherPubs",
        'translations'       => "\$translationsPlain",
        'illustrations'      => "\$illustrations",
        'figureUrls'         => "\$figureUrls",
        'blOnline'           => "\$blOnline",
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
            throw new \RuntimeException(
                'XQuery search failed: ' . $e->getMessage() . "\n--- XQuery ---\n" . $xquery,
                (int)$e->getCode(),
                $e
            );
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

        // Look up keyword translations for the detail view (toggleable display).
        if (!empty($data['keywords'])) {
            $data['keywordTranslations'] = $this->lookupKeywordTranslations($data['keywords']);
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
  for \$doc in db:get('hgv')/tei:TEI
  let \$pub := (\$doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
  let \$origDates := \$doc//tei:history/tei:origin/tei:origDate
  let \$provenances := \$doc//tei:provenance
  where $whereInner
  return map {
    "id":  string(\$doc//tei:idno[@type='filename']),
    "tm":  string(\$doc//tei:idno[@type='TM']),
    "ddb": string(\$doc//tei:idno[@type='ddb-hybrid']),
    "publ":    normalize-space(string-join((string(\$pub/tei:title[@type='abbreviated']), \$pub/tei:biblScope), ' ')),
    "pubAbbr": string(\$pub/tei:title[@type='abbreviated']),
    "pubVol":  string(\$pub/tei:biblScope[@type='volume']),
    "pubNr":   string(\$pub/tei:biblScope[@type='numbers']),
    "dating":  normalize-space(string((\$origDates)[1])),
    "place":   string(\$doc//tei:origPlace),
    "title":   string(\$doc//tei:titleStmt/tei:title),
    "material": string(\$doc//tei:material),
    "dates": array {
      for \$od in \$origDates
      return map {
        "xmlId":     string(\$od/@xml:id),
        "dating":    normalize-space(string(\$od)),
        "notBefore": string((\$od/@notBefore, \$od/@when)[1]),
        "notAfter":  string((\$od/@notAfter,  \$od/@when)[1]),
        "when":      string(\$od/@when),
        "precision": string((\$od/@precision, \$od/@cert)[1])
      }
    },
    "provenances": array {
      for \$prov in \$provenances
      return map {
        "xmlId":  string((\$prov/tei:p/@xml:id)[1]),
        "type":   string(\$prov/@type),
        "place":  string-join(\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ', '),
        "placeRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else ''),
        "nome":   string((\$prov/tei:p/tei:placeName[@subtype='nome'])[1]),
        "nomeRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@subtype='nome']/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else ''),
        "region": string((\$prov/tei:p/tei:placeName[@subtype='region'])[1]),
        "regionRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@subtype='region']/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else '')
      }
    }
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
  let $pubAbbr      := string(($pub/tei:title[@type='abbreviated'])[1])
  let $pubVol       := string(($pub/tei:biblScope[@type='volume'])[1])
  let $pubNr        := string(($pub/tei:biblScope[@type='numbers'])[1])
  let $origDates    := $doc//tei:history/tei:origin/tei:origDate
  let $origDate     := $origDates[1]
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
  let $hgvId        := string(($doc//tei:idno[@type='filename'])[1])
  let $sortHgvNum   := let $d := replace($hgvId, '[^0-9]', '')
                       return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $sortHgvSuffix := replace($hgvId, '[0-9]', '')
  let $sortPubVol   := let $d := replace($pubVol, '[^0-9]', '')
                       return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $sortPubNr    := let $d := replace(replace($pubNr, '^[^0-9]*', ''), '[^0-9].*$', '')
                       return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $ddbHybrid    := string(($doc//tei:idno[@type='ddb-hybrid'])[1])
  let $ddbParts     := tokenize($ddbHybrid, ';')
  let $sortDdbSer   := string($ddbParts[1])
  let $sortDdbVol   := let $d := replace(string($ddbParts[2]), '[^0-9]', '')
                       return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $sortDdbDoc   := let $d := replace(string($ddbParts[3]), '[^0-9]', '')
                       return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $when         := string($origDate/@when)
  let $precision    := string(($origDate/@precision, $origDate/@cert)[1])
  let $settlement   := string(($doc//tei:msIdentifier/tei:placeName/tei:settlement)[1])
  let $collection   := string(($doc//tei:msIdentifier/tei:placeName/tei:collection)[1])
  let $invNo        := string(($doc//tei:msIdentifier/tei:idno[@type='invNo'])[1])
  let $provenance   := string-join($doc//tei:provenance[@type='located']//tei:placeName[@type='ancient']/text(), ' – ')
  let $provenancePlace := string-join($doc//tei:provenance/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ' – ')
  let $provenanceNome  := string-join($doc//tei:provenance/tei:p/tei:placeName[@subtype='nome']/text(), ' – ')
  let $mentionedDatesText := string(($doc//tei:div[@type='commentary'][@subtype='mentionedDates']/tei:note[@type='original'])[1])
  let $figureUrls   := string-join($doc//tei:figure/tei:graphic/string(@url), '; ')
  let $blOnline     := string-join(
    for $bl in $doc//tei:div[@type='bibliography'][@subtype='corrections']//tei:bibl[@type='BL']
    return concat('BL ', string($bl/tei:biblScope[@type='volume']), if (string($bl/tei:biblScope[@type='pages']) != '') then concat(', S. ', string($bl/tei:biblScope[@type='pages'])) else ''),
    '; ')
  let $provenances  := $doc//tei:provenance
  let $datesMap     := array {
    for $od in $origDates
    return map {
      "xmlId":     string($od/@xml:id),
      "dating":    normalize-space(string($od)),
      "notBefore": string(($od/@notBefore, $od/@when)[1]),
      "notAfter":  string(($od/@notAfter,  $od/@when)[1]),
      "when":      string($od/@when),
      "precision": string(($od/@precision, $od/@cert)[1])
    }
  }
  let $provenancesMap := array {
    for $prov in $provenances
    return map {
      "xmlId":  string(($prov/tei:p/@xml:id)[1]),
      "type":   string($prov/@type),
      "place":  string-join($prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ', '),
      "placeRef": (let $t := (for $r in tokenize(string(($prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/@ref)[1]), '\s+') where contains($r, 'trismegistos.org/') return $r)[1] return if (starts-with($t, 'http')) then $t else if ($t != '') then concat('https://', $t) else ''),
      "nome":   string(($prov/tei:p/tei:placeName[@subtype='nome'])[1]),
      "nomeRef": (let $t := (for $r in tokenize(string(($prov/tei:p/tei:placeName[@subtype='nome']/@ref)[1]), '\s+') where contains($r, 'trismegistos.org/') return $r)[1] return if (starts-with($t, 'http')) then $t else if ($t != '') then concat('https://', $t) else ''),
      "region": string(($prov/tei:p/tei:placeName[@subtype='region'])[1]),
      "regionRef": (let $t := (for $r in tokenize(string(($prov/tei:p/tei:placeName[@subtype='region']/@ref)[1]), '\s+') where contains($r, 'trismegistos.org/') return $r)[1] return if (starts-with($t, 'http')) then $t else if ($t != '') then concat('https://', $t) else '')
    }
  }
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

let \$total := count(db:get('hgv')/tei:TEI)
let \$filtered :=
  count(
    for \$doc in db:get('hgv')/tei:TEI
$bindings$whereStr
    return 1
  )
return map { "total": \$total, "filtered": \$filtered }
XQ;
    }

    /**
     * Build full search-and-paginate XQuery returning
     * {"total": N, "filtered": M, "data": [...]}.
     *
     * Uses a two-phase approach:
     *  Phase 1 – lightweight scan of all records; returns sorted/filtered document
     *            node references.  When no WHERE clause is active only the minimal
     *            sort-key bindings are computed (avoids string-join() on 65k docs).
     *  Phase 2 – builds the full output map only for the requested page ($limit docs).
     */
    private function buildSearchXQuery(string $where, string $order, int $offset, int $limit): string
    {
        // When filtering, every binding may be referenced by WHERE; use full set.
        // When only sorting, use the minimal set to avoid expensive string-join() calls.
        $phase1Bindings = ($where !== '') ? $this->commonLetBindings() : $this->phase1Bindings($order);
        $phase2Bindings = $this->commonLetBindings();

        $whereStr = $where ? "\n  $where" : '';
        $orderStr = $order ? "\n  $order" : '';

        return <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";

(: Phase 1 – lightweight scan; collect sorted/filtered document node references :)
let \$total := count(db:get('hgv')/tei:TEI)
let \$sorted :=
  for \$doc in db:get('hgv')/tei:TEI
$phase1Bindings$whereStr$orderStr
  return \$doc
let \$filtered := count(\$sorted)

(: Phase 2 – build output maps only for the requested page :)
let \$data :=
  for \$doc in subsequence(\$sorted, $offset, $limit)
$phase2Bindings
  return map {
    "id":       string((\$doc//tei:idno[@type='filename'])[1]),
    "tm":       string((\$doc//tei:idno[@type='TM'])[1]),
    "ddb":      string((\$doc//tei:idno[@type='ddb-hybrid'])[1]),
    "publ":     normalize-space(string-join((\$pubAbbr, \$pub/tei:biblScope), ' ')),
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
    "illustrations": \$illustrations,
    "when":          \$when,
    "precision":     \$precision,
    "settlement":    \$settlement,
    "collection":    \$collection,
    "invNo":         \$invNo,
    "provenance":    \$provenance,
    "provenancePlace": \$provenancePlace,
    "provenanceNome":  \$provenanceNome,
    "translations":  \$translationsPlain,
    "mentionedDatesText": \$mentionedDatesText,
    "figureUrls":    \$figureUrls,
    "blOnline":      \$blOnline,
    "dates":         \$datesMap,
    "provenances":   \$provenancesMap
  }
return map {
  "total":    \$total,
  "filtered": \$filtered,
  "data":     array { \$data }
}
XQ;
    }

    /**
     * Minimal let-bindings for phase 1 when there is no WHERE clause.
     * Only computes the expressions actually needed by ORDER BY, skipping all the
     * expensive string-join() calls that are only required for output fields.
     */
    private function phase1Bindings(string $order): string
    {
        // Always include publication fields and year/tm sort keys (default sort).
        $base = <<<'XQB'
  let $pub       := ($doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
  let $pubAbbr   := string(($pub/tei:title[@type='abbreviated'])[1])
  let $pubVol    := string(($pub/tei:biblScope[@type='volume'])[1])
  let $pubNr     := string(($pub/tei:biblScope[@type='numbers'])[1])
  let $origDates := $doc//tei:history/tei:origin/tei:origDate
  let $origDate  := $origDates[1]
  let $notBefore := string(($origDate/@notBefore, $origDate/@when)[1])
  let $notAfter  := string(($origDate/@notAfter,  $origDate/@when)[1])
  let $sortYear  := if ($notBefore != '' and $notBefore castable as xs:integer)
                    then xs:integer($notBefore)
                    else if ($notAfter != '' and $notAfter castable as xs:integer)
                    then xs:integer($notAfter)
                    else 9999
  let $sortTm    := if (string(($doc//tei:idno[@type='TM'])[1]) castable as xs:integer)
                    then xs:integer(string(($doc//tei:idno[@type='TM'])[1]))
                    else 0
  let $hgvId     := string(($doc//tei:idno[@type='filename'])[1])
  let $sortHgvNum := let $d := replace($hgvId, '[^0-9]', '')
                     return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $sortHgvSuffix := replace($hgvId, '[0-9]', '')
  let $sortPubVol := let $d := replace($pubVol, '[^0-9]', '')
                     return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $sortPubNr  := let $d := replace(replace($pubNr, '^[^0-9]*', ''), '[^0-9].*$', '')
                     return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $ddbHybrid := string(($doc//tei:idno[@type='ddb-hybrid'])[1])
  let $ddbParts  := tokenize($ddbHybrid, ';')
  let $sortDdbSer := string($ddbParts[1])
  let $sortDdbVol := let $d := replace(string($ddbParts[2]), '[^0-9]', '')
                     return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0
  let $sortDdbDoc := let $d := replace(string($ddbParts[3]), '[^0-9]', '')
                     return if ($d != '' and $d castable as xs:integer) then xs:integer($d) else 0

XQB;
        // Only add extra bindings when the ORDER BY clause actually references them.
        $optional = [
            '$place'             => "  let \$place    := string((\$doc//tei:origPlace)[1])\n",
            '$title'             => "  let \$title    := string((\$doc//tei:titleStmt/tei:title)[1])\n",
            '$material'          => "  let \$material := string((\$doc//tei:material)[1])\n",
            '$dating'            => "  let \$dating   := normalize-space(string(\$origDate))\n",
            '$when'              => "  let \$when     := string(\$origDate/@when)\n",
            '$precision'         => "  let \$precision := string((\$origDate/@precision, \$origDate/@cert)[1])\n",
            '$settlement'        => "  let \$settlement := string((\$doc//tei:msIdentifier/tei:placeName/tei:settlement)[1])\n",
            '$collection'        => "  let \$collection := string((\$doc//tei:msIdentifier/tei:placeName/tei:collection)[1])\n",
            '$invNo'             => "  let \$invNo    := string((\$doc//tei:msIdentifier/tei:idno[@type='invNo'])[1])\n",
            '$keywords'          => "  let \$keywords := string-join(\$doc//tei:keywords[@scheme='hgv']/tei:term/text(), '; ')\n",
            '$otherPubs'         => "  let \$otherPubs := string-join(\$doc//tei:bibl[@type='publication'][@subtype='other']/text(), '; ')\n",
            '$illustrations'     => "  let \$illustrations := string-join(\$doc//tei:bibl[@type='illustration']/text(), '; ')\n",
            '$commentary'        => "  let \$commentary := string-join(\$doc//tei:div[@type='commentary'][@subtype='general']/tei:p/text(), ' ')\n",
            '$translationsPlain' => "  let \$translationsPlain := string-join(\$doc//tei:div[@type='bibliography'][@subtype='translations']//tei:bibl[@type='translations']/text(), '; ')\n",
            '$mentionedDatesText'    => "  let \$mentionedDatesText := string((\$doc//tei:div[@type='commentary'][@subtype='mentionedDates']/tei:note[@type='original'])[1])\n",
            '$provenance'        => "  let \$provenances := \$doc//tei:provenance\n  let \$provenance := string-join(\$doc//tei:provenance[@type='located']//tei:placeName[@type='ancient']/text(), ' \u2013 ')\n",
            '$provenancePlace'   => "  let \$provenancePlace := string-join(\$doc//tei:provenance/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ' \u2013 ')\n",
            '$provenanceNome'    => "  let \$provenanceNome := string-join(\$doc//tei:provenance/tei:p/tei:placeName[@subtype='nome']/text(), ' \u2013 ')\n",
            '$figureUrls'        => "  let \$figureUrls := string-join(\$doc//tei:figure/tei:graphic/string(@url), '; ')\n",
            '$blOnline'          => "  let \$blOnline := string-join(\n    for \$bl in \$doc//tei:div[@type='bibliography'][@subtype='corrections']//tei:bibl[@type='BL']\n    return concat('BL ', string(\$bl/tei:biblScope[@type='volume']), ', S. ', string(\$bl/tei:biblScope[@type='pages'])),\n    '; ')\n",
        ];
        $extra = '';
        foreach ($optional as $varName => $binding) {
            if (str_contains($order, $varName)) {
                $extra .= $binding;
            }
        }
        return $base . $extra;
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

let \$doc := (db:get('hgv')/tei:TEI[
  tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='filename'] = '$safeId'
])[1]
return
  if (\$doc)
  then
    let \$pub      := (\$doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
    let \$pubAbbr  := string((\$pub/tei:title[@type='abbreviated'])[1])
    let \$pubVol   := string((\$pub/tei:biblScope[@type='volume'])[1])
    let \$pubNr    := string((\$pub/tei:biblScope[@type='numbers'])[1])
    let \$ddb      := string((\$doc//tei:idno[@type='ddb-hybrid'])[1])
    let \$ddbParts := tokenize(\$ddb, ';')
    let \$origDates := \$doc//tei:history/tei:origin/tei:origDate
    let \$origDate  := \$origDates[1]
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
    let \$provenances := \$doc//tei:provenance
    return map {
      "id":         string((\$doc//tei:idno[@type='filename'])[1]),
      "tm":         string((\$doc//tei:idno[@type='TM'])[1]),
      "ddb":        \$ddb,
      "ddbSer":     string(\$ddbParts[1]),
      "ddbVol":     string(\$ddbParts[2]),
      "ddbDoc":     string(\$ddbParts[3]),
      "publ":       normalize-space(string-join((\$pubAbbr, \$pub/tei:biblScope), ' ')),
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
      "mentionedDatesText": string((\$doc//tei:div[@type='commentary'][@subtype='mentionedDates']/tei:note[@type='original'])[1]),
      "provenance": \$provenance,
      "dates": array {
        for \$od in \$origDates
        return map {
          "xmlId":     string(\$od/@xml:id),
          "dating":    normalize-space(string(\$od)),
          "notBefore": string((\$od/@notBefore, \$od/@when)[1]),
          "notAfter":  string((\$od/@notAfter,  \$od/@when)[1]),
          "when":      string(\$od/@when),
          "precision": string((\$od/@precision, \$od/@cert)[1])
        }
      },
      "provenances": array {
        for \$prov in \$provenances
        return map {
          "xmlId":  string((\$prov/tei:p/@xml:id)[1]),
          "type":   string(\$prov/@type),
          "place":  string-join(\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ', '),
          "placeRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else ''),
          "nome":   string((\$prov/tei:p/tei:placeName[@subtype='nome'])[1]),
          "nomeRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@subtype='nome']/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else ''),
          "region": string((\$prov/tei:p/tei:placeName[@subtype='region'])[1]),
          "regionRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@subtype='region']/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else '')
        }
      },
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
     * For multi-date fields (dating, when, precision, notBefore, notAfter, …)
     * the condition is wrapped with "some $od in $origDates satisfies (…)" so
     * that ALL origDate elements in a record are checked.
     * For multi-provenance fields the same pattern is used with
     * "some $prov in $provenances satisfies (…)".
     *
     * Returns null when the field is unknown.
     */
    private function buildCondition(string $field, string $op, string $value): ?string
    {
        // Check if this is a multi-date or multi-provenance field
        $multiDateExpr = self::MULTI_DATE_FIELD_EXPR[$field] ?? null;
        $multiProvExpr = self::MULTI_PROVENANCE_FIELD_EXPR[$field] ?? null;

        // Determine the quantifier variable/collection for "some ... satisfies" wrapping
        $quantVar  = null;  // e.g. '$od' or '$prov'
        $quantColl = null;  // e.g. '$origDates' or '$provenances'
        $multiExpr = null;
        if ($multiDateExpr) {
            $quantVar  = '$od';
            $quantColl = '$origDates';
            $multiExpr = $multiDateExpr;
        } elseif ($multiProvExpr) {
            $quantVar  = '$prov';
            $quantColl = '$provenances';
            $multiExpr = $multiProvExpr;
        }

        // Special wildcard operators
        if ($value === '*') {
            if ($multiExpr) {
                return "(some $quantVar in $quantColl satisfies ($multiExpr != ''))";
            }
            $expr = self::FIELD_EXPR[$field] ?? null;
            if (!$expr) return null;
            return "($expr != '')";
        }
        if ($value === '=') {
            if ($multiExpr) {
                return "(not($quantColl) or (every $quantVar in $quantColl satisfies ($multiExpr = '' or not($multiExpr))))";
            }
            $expr = self::FIELD_EXPR[$field] ?? null;
            if (!$expr) return null;
            return "($expr = '' or not($expr))";
        }

        // Date range: "100...200"
        if (preg_match('/^(-?\d+)\.{2,}(-?\d+)$/', $value, $m)) {
            return $this->buildDateRangeCondition($field, (int)$m[1], (int)$m[2]);
        }

        // Use multi expression if available, otherwise regular FIELD_EXPR
        $expr = $multiExpr ?? (self::FIELD_EXPR[$field] ?? null);
        if (!$expr) {
            return null; // unknown field — skip silently
        }

        // Splittersuche (operator 'sp'): all words must appear
        if ($op === 'sp') {
            $parts = [];
            foreach (preg_split('/\s+/', trim($value)) as $word) {
                if ($word === '') continue;
                $safeWord = $this->escXQ($word);
                $wordCond = "contains(lower-case($expr), lower-case('$safeWord'))";
                if ($field === 'keywords') {
                    $wordCond = $this->augmentKeywordCondition($wordCond, $word, 'cn');
                }
                $parts[] = $wordCond;
            }
            if (empty($parts)) return null;
            $inner = '(' . implode(' and ', $parts) . ')';
            if ($multiExpr) {
                return "(some $quantVar in $quantColl satisfies $inner)";
            }
            return $inner;
        }

        $safeVal = $this->escXQ($value);
        $isNumeric = in_array($field, self::NUMERIC_FIELDS, true);

        $cond = null;
        switch ($op) {
            case 'cn': $cond = "contains(lower-case($expr), lower-case('$safeVal'))"; break;
            case 'bw': $cond = "starts-with(lower-case($expr), lower-case('$safeVal'))"; break;
            case 'ew': $cond = "ends-with(lower-case($expr), lower-case('$safeVal'))"; break;
            case 'eq':
                $cond = ($isNumeric && is_numeric($value))
                    ? "($expr castable as xs:integer and xs:integer($expr) = $safeVal)"
                    : "lower-case($expr) = lower-case('$safeVal')";
                break;
            case 'neq':
                // "not equal" for multi-value: none of the items match the value
                if ($multiExpr) {
                    $eqCond = ($isNumeric && is_numeric($value))
                        ? "($expr castable as xs:integer and xs:integer($expr) = $safeVal)"
                        : "lower-case($expr) = lower-case('$safeVal')";
                    return "(not(some $quantVar in $quantColl satisfies $eqCond))";
                }
                $cond = ($isNumeric && is_numeric($value))
                    ? "($expr castable as xs:integer and xs:integer($expr) != $safeVal)"
                    : "lower-case($expr) != lower-case('$safeVal')";
                break;
            case 'lt':
                $cond = ($isNumeric && is_numeric($value))
                    ? "($expr castable as xs:integer and xs:integer($expr) < $safeVal)"
                    : "$expr < '$safeVal'";
                break;
            case 'lte':
                $cond = ($isNumeric && is_numeric($value))
                    ? "($expr castable as xs:integer and xs:integer($expr) <= $safeVal)"
                    : "$expr <= '$safeVal'";
                break;
            case 'gt':
                $cond = ($isNumeric && is_numeric($value))
                    ? "($expr castable as xs:integer and xs:integer($expr) > $safeVal)"
                    : "$expr > '$safeVal'";
                break;
            case 'gte':
                $cond = ($isNumeric && is_numeric($value))
                    ? "($expr castable as xs:integer and xs:integer($expr) >= $safeVal)"
                    : "$expr >= '$safeVal'";
                break;
        }

        if ($cond === null) return null;

        // For keyword searches, OR-extend the condition so that hits in any of
        // the translated languages (fr/en/es/it) also match. We look up German
        // equivalents from the `keywords` BaseX database and append a
        // contains() check against $keywords (a '; '-joined German string).
        // Skipped for 'neq' to preserve its German-only "does not equal" semantics.
        if ($field === 'keywords' && $op !== 'neq') {
            $cond = $this->augmentKeywordCondition($cond, $value, $op);
        }

        if ($multiExpr) {
            return "(some $quantVar in $quantColl satisfies $cond)";
        }
        return $cond;
    }

    // ── Multi-language keyword lookup helpers ────────────────────────────────────

    /** Per-request cache of German-term lookups, keyed by "op|lower(value)". */
    private array $germanTermCache = [];

    /**
     * Extend a single keyword-search condition with extra OR clauses that match
     * German terms whose translation (fr/en/es/it) satisfies the user's input.
     *
     * The German equivalents are appended as contains() checks against the
     * existing $keywords binding so that no extra per-document XQuery cost
     * is incurred — the lookup runs once per search against the small
     * `keywords` database.
     */
    private function augmentKeywordCondition(string $baseCond, string $value, string $op): string
    {
        $germanTerms = $this->findGermanTermsMatching($value, $op);
        if (empty($germanTerms)) {
            return $baseCond;
        }

        $orParts = [$baseCond];
        foreach ($germanTerms as $de) {
            $safeDe    = $this->escXQ($de);
            $orParts[] = "contains(lower-case(\$keywords), lower-case('$safeDe'))";
        }
        return '(' . implode(' or ', $orParts) . ')';
    }

    /**
     * Look up German keyword terms whose fr/en/es/it translation matches the
     * user's search value under the given operator. Returns [] if the
     * `keywords` BaseX database is missing or the query fails (graceful
     * fallback to German-only search).
     *
     * @return string[]
     */
    private function findGermanTermsMatching(string $value, string $op): array
    {
        $value = trim($value);
        if ($value === '' || $value === '*' || $value === '=') {
            return [];
        }

        $cacheKey = $op . '|' . mb_strtolower($value);
        if (isset($this->germanTermCache[$cacheKey])) {
            return $this->germanTermCache[$cacheKey];
        }

        $safe  = $this->escXQ($value);
        $preds = [];
        foreach (['fr', 'en', 'es', 'it'] as $lang) {
            $attr = "string(\$kw/@$lang)";
            switch ($op) {
                case 'bw':
                    $preds[] = "starts-with(lower-case($attr), lower-case('$safe'))";
                    break;
                case 'ew':
                    $preds[] = "ends-with(lower-case($attr), lower-case('$safe'))";
                    break;
                case 'eq':
                    $preds[] = "lower-case($attr) = lower-case('$safe')";
                    break;
                case 'cn':
                default:
                    $preds[] = "contains(lower-case($attr), lower-case('$safe'))";
                    break;
            }
        }
        $pred = implode(' or ', $preds);

        $xquery = <<<XQ
declare option output:method "json";
array {
  if (db:exists('keywords'))
  then
    for \$kw in db:get('keywords')//kw
    where $pred
    return string(\$kw/@de)
  else ()
}
XQ;

        try {
            $rows = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return $this->germanTermCache[$cacheKey] = [];
        }

        $terms = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $term = is_array($row) ? '' : (string)$row;
                if ($term !== '') {
                    $terms[] = $term;
                }
            }
        }
        return $this->germanTermCache[$cacheKey] = array_values(array_unique($terms));
    }

    /**
     * Look up fr/en/es/it translations for the German keyword terms attached
     * to a single record. Returns a per-language map of '; '-joined strings
     * (mirroring the format of $record->getKeywords()), with missing
     * translations falling back to the original German term.
     *
     * @return array{fr?: string, en?: string, es?: string, it?: string}
     */
    private function lookupKeywordTranslations(string $joinedGerman): array
    {
        $terms = array_values(array_filter(
            array_map('trim', explode(';', $joinedGerman)),
            static fn(string $t): bool => $t !== ''
        ));
        if (empty($terms)) {
            return [];
        }

        $seq = implode(', ', array_map(
            fn(string $t): string => "'" . $this->escXQ($t) . "'",
            $terms
        ));

        $xquery = <<<XQ
declare option output:method "json";
array {
  if (db:exists('keywords'))
  then
    for \$de in ($seq)
    let \$kw := (db:get('keywords')//kw[@de = \$de])[1]
    return map {
      "de": string(\$de),
      "fr": string(\$kw/@fr),
      "en": string(\$kw/@en),
      "es": string(\$kw/@es),
      "it": string(\$kw/@it)
    }
  else ()
}
XQ;

        try {
            $rows = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return [];
        }
        if (!is_array($rows) || empty($rows)) {
            return [];
        }
        // Single-element JSON arrays may come back as a bare map
        if (isset($rows['de'])) {
            $rows = [$rows];
        }

        $byLang = ['fr' => [], 'en' => [], 'es' => [], 'it' => []];
        $hasAny = false;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $de = (string)($row['de'] ?? '');
            foreach (['fr', 'en', 'es', 'it'] as $lang) {
                $val = (string)($row[$lang] ?? '');
                if ($val !== '') {
                    $hasAny = true;
                } else {
                    // Fall back to the German term so the joined strings stay aligned
                    $val = $de;
                }
                $byLang[$lang][] = $val;
            }
        }

        if (!$hasAny) {
            return [];
        }
        return array_map(
            static fn(array $parts): string => implode('; ', $parts),
            $byLang
        );
    }

    /**
     * Build numeric year-range condition for date fields.
     * Checks across all origDate elements (multi-date aware).
     */
    private function buildDateRangeCondition(string $field, int $from, int $to): ?string
    {
        $odExpr = match ($field) {
            'notBefore', 'year'    => "string((\$od/@notBefore, \$od/@when)[1])",
            'notAfter'             => "string((\$od/@notAfter, \$od/@when)[1])",
            'sortYear'             => "string((\$od/@notBefore, \$od/@when)[1])",
            default                => null,
        };
        if (!$odExpr) return null;
        return "(some \$od in \$origDates satisfies ($odExpr castable as xs:integer and xs:integer($odExpr) >= $from and xs:integer($odExpr) <= $to))";
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
  for \$doc in db:get('hgv')/tei:TEI[
    tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='$safeType'] = '$safeValue'
  ]
  let \$pub      := (\$doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
  let \$pubAbbr  := string(\$pub/tei:title[@type='abbreviated'])
  let \$pubVol   := string(\$pub/tei:biblScope[@type='volume'])
  let \$pubNr    := string(\$pub/tei:biblScope[@type='numbers'])
  let \$origDates := \$doc//tei:history/tei:origin/tei:origDate
  let \$origDate  := \$origDates[1]
  let \$provenances := \$doc//tei:provenance
  order by string((\$doc//tei:idno[@type='filename'])[1]) ascending
  return map {
    "id":       string((\$doc//tei:idno[@type='filename'])[1]),
    "tm":       string((\$doc//tei:idno[@type='TM'])[1]),
    "ddb":      string((\$doc//tei:idno[@type='ddb-hybrid'])[1]),
    "publ":     normalize-space(string-join((\$pubAbbr, \$pub/tei:biblScope), ' ')),
    "pubAbbr":  \$pubAbbr,
    "pubVol":   \$pubVol,
    "pubNr":    \$pubNr,
    "dating":   normalize-space(string(\$origDate)),
    "notBefore": string((\$origDate/@notBefore, \$origDate/@when)[1]),
    "notAfter":  string((\$origDate/@notAfter,  \$origDate/@when)[1]),
    "place":    string((\$doc//tei:origPlace)[1]),
    "title":    string((\$doc//tei:titleStmt/tei:title)[1]),
    "material": string((\$doc//tei:material)[1]),
    "keywords": string-join(\$doc//tei:keywords[@scheme='hgv']/tei:term/text(), '; '),
    "dates": array {
      for \$od in \$origDates
      return map {
        "xmlId":     string(\$od/@xml:id),
        "dating":    normalize-space(string(\$od)),
        "notBefore": string((\$od/@notBefore, \$od/@when)[1]),
        "notAfter":  string((\$od/@notAfter,  \$od/@when)[1]),
        "when":      string(\$od/@when),
        "precision": string((\$od/@precision, \$od/@cert)[1])
      }
    },
    "provenances": array {
      for \$prov in \$provenances
      return map {
        "xmlId":  string((\$prov/tei:p/@xml:id)[1]),
        "type":   string(\$prov/@type),
        "place":  string-join(\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/text(), ', '),
        "placeRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@type='ancient'][not(@subtype)]/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else ''),
        "nome":   string((\$prov/tei:p/tei:placeName[@subtype='nome'])[1]),
        "nomeRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@subtype='nome']/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else ''),
        "region": string((\$prov/tei:p/tei:placeName[@subtype='region'])[1]),
        "regionRef": (let \$t := (for \$r in tokenize(string((\$prov/tei:p/tei:placeName[@subtype='region']/@ref)[1]), '\s+') where contains(\$r, 'trismegistos.org/') return \$r)[1] return if (starts-with(\$t, 'http')) then \$t else if (\$t != '') then concat('https://', \$t) else '')
      }
    }
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
let \$doc := (db:get('ddb')/tei:TEI[
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
