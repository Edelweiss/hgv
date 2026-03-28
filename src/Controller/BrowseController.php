<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

use App\Service\HgvXmlService;
use App\Service\MatomoReport;

class BrowseController extends HgvController
{
    private HgvXmlService $xmlService;

    public function __construct(RequestStack $requestStack, HgvXmlService $xmlService)
    {
        parent::__construct($requestStack);
        $this->xmlService = $xmlService;
    }

    /** Fields shown in the advanced search form. */
    static $FIELD_LIST_SEARCH = [
        'publication'       => 'Publikation',
        'volume'            => 'Band',
        'number'            => 'Nummer',
        'tm'                => 'TM Nr.',
        'year'              => 'Jahr',
        'century'           => 'Jahrhundert',
        'place'             => 'Ort',
        'title'             => 'Originaltitel',
        'material'          => 'Material',
        'illustrations'     => 'Abbildung',
        'otherPublications' => 'Andere Publikation',
        'commentary'        => 'Bemerkungen',
        'keywords'          => 'Inhalt',
        'url'               => 'Link',
        'notBefore'         => 'Chron-Minimum',
        'notAfter'          => 'Chron-Maximum',
        'sortYear'          => 'Chron-Global',
        'translations'      => 'Übersetzungen',
    ];

    /** Used for the single-record field labels. */
    static $FIELD_LIST_SINGLE = [
        'publication'       => 'Publikation',
        'dating'            => 'Datierung',
        'place'             => 'Ort',
        'title'             => 'Originaltitel',
        'material'          => 'Material',
        'illustrations'     => 'Abbildung',
        'ddbVol'            => 'Texte der DDBDP',
    ];

    static $OPERATOR_LIST = [
        'cn'  => 'Enthält',
        'bw'  => 'Beginnt mit',
        'ew'  => 'Endet mit',
        'eq'  => 'Ist gleich',
        'neq' => 'Ist ungleich',
        'lt'  => 'Kleiner',
        'lte' => 'Kleiner oder gleich',
        'gt'  => 'Größer',
        'gte' => 'Größer oder gleich',
    ];

    // ── Controller actions ────────────────────────────────────────────────────────

    /** Show search form. */
    public function search(MatomoReport $matomo): Response
    {
        return $this->render('browse/search.html.twig', [
            'matomo'    => $matomo,
            'fieldList' => self::$FIELD_LIST_SEARCH,
        ]);
    }

    /** Show single data record (by position within current search result set). */
    public function single(): Response
    {
        $search = $this->getParameterSearch();
        $sort   = $this->getParameterSort();
        $show   = $this->getParameterShow();

        // Merge show-offset into search so searchPaginated() uses skip/max from $show
        $searchAtPos = array_merge($search, ['skip' => $show['skip'], 'max' => 1]);
        $result = $this->xmlService->searchPaginated($searchAtPos, $sort);

        $record = $result['data'][0] ?? null;

        // For the single view we need the fully-detailed record (incl. BL, DDB text, …)
        if ($record !== null) {
            $record = $this->xmlService->getFullRecord($record->getId()) ?? $record;
        }

        return $this->render('browse/single.html.twig', [
            'search'      => $search,
            'sort'        => $sort,
            'show'        => $show,
            'showPrev'    => ($show['skip'] > 0
                            ? array_merge($show, ['skip' => $show['skip'] - 1])
                            : null),
            'showNext'    => ($show['skip'] < ($result['filtered'] - 1)
                            ? array_merge($show, ['skip' => $show['skip'] + 1])
                            : null),
            'fieldList'   => self::$FIELD_LIST_SINGLE,
            'countTotal'  => $result['total'],
            'countSearch' => $result['filtered'],
            'record'      => $record,
        ]);
    }

    /** Render the browse-list shell page (data loaded via AJAX by multipleApi). */
    public function multiple(): Response
    {
        $search = $this->getParameterSearch();
        $sort   = $this->getParameterSort();

        $this->setSessionParameter('search', $search);
        $this->setSessionParameter('sort', $sort);

        $counts = $this->xmlService->countRecords(
            $search['criteria'] ?? [],
            $search['operator'] ?? 'and'
        );

        return $this->render('browse/multi.html.twig', [
            'search'      => $search,
            'countTotal'  => $counts['total'],
            'countSearch' => $counts['filtered'],
        ]);
    }

    // ── DataTables server-side processing endpoint ────────────────────────────────

    /**
     * AJAX endpoint consumed by browseMulti.js (DataTables server-side processing).
     * Returns JSON: { draw, recordsTotal, recordsFiltered, data: [...] }
     */
    public function multipleApi(): JsonResponse
    {
        $draw   = (int)$this->request->query->get('draw', 1);
        $start  = (int)$this->request->query->get('start', 0);
        $length = (int)$this->request->query->get('length', 25);

        // ── JS data field → PHP sort/search key ──────────────────────────────────
        // Maps the DataTables column 'data' property to the key used by
        // HgvXmlService SORT_EXPR / FIELD_EXPR.  This is robust against
        // ColReorder: instead of relying on column indices (which shift when
        // the user drags columns), we look up columns[i][data] to identify
        // the actual field.
        $dataFieldMap = [
            'publ'            => 'publication',
            'dating'          => 'dating',
            'place'           => 'place',
            'title'           => 'title',
            'material'        => 'material',
            'keywords'        => 'keywords',
            'otherPub'        => 'otherPublications',
            'tm'              => 'tm',
            'ddb'             => 'ddb',
            'hgvId'           => 'hgv',
            'pubAbbr'         => 'pubAbbr',
            'pubVol'          => 'pubVol',
            'pubNr'           => 'pubNr',
            'notBefore'       => 'notBefore',
            'notAfter'        => 'notAfter',
            'when'            => 'when',
            'precision'       => 'precision',
            'settlement'      => 'settlement',
            'collection'      => 'collection',
            'invNo'           => 'invNo',
            'provenance'      => 'provenance',
            'provenancePlace' => 'provenancePlace',
            'provenanceNome'  => 'provenanceNome',
            'illustrations'   => 'illustrations',
            'figureUrls'      => 'figureUrls',
            'translations'    => 'translations',
            'commentary'      => 'commentary',
            'mentionedDates'  => 'mentionedDatesText',
            'blOnline'        => 'blOnline',
        ];

        // Build a runtime map: column-index → PHP key, based on the 'data'
        // property that DataTables sends for each column.  This map adapts
        // automatically if ColReorder has changed column positions.
        $columns = $this->request->query->all('columns');
        $colIndexToKey = [];
        foreach ($columns as $idx => $col) {
            $dataField = $col['data'] ?? '';
            $colIndexToKey[(int)$idx] = $dataFieldMap[$dataField] ?? null;
        }

        // ── Build sort ────────────────────────────────────────────────────────────
        $sort = [];
        $idx  = 1;
        foreach ($this->request->query->all('order') as $orderItem) {
            $colIdx = (int)($orderItem['column'] ?? 0);
            $key    = $colIndexToKey[$colIdx] ?? null;
            if ($key !== null) {
                $sort[$idx++] = [
                    'key'       => $key,
                    'direction' => ($orderItem['dir'] ?? 'asc') === 'desc' ? 'descend' : 'ascend',
                ];
            }
        }
        if (empty($sort)) {
            $sort = [1 => ['key' => 'sortYear', 'direction' => 'ascend']];
        }

        // ── Build per-column search criteria (override session) ───────────────────
        $columnCriteria = [];
        foreach ($columns as $colIdx => $col) {
            $key = $colIndexToKey[(int)$colIdx] ?? null;
            $val = trim($col['search']['value'] ?? '');
            if ($key !== null && $val !== '') {
                $columnCriteria[$key] = ['operator' => 'cn', 'value' => $val];
            }
        }

        // ── Merge with session search (from advanced search form) ─────────────────
        $sessionSearch = $this->getSessionParameter('search') ?? [];
        $baseCriteria  = $sessionSearch['criteria'] ?? [];
        $operator      = $sessionSearch['operator']  ?? 'and';

        // Global DataTables search (if no per-column filters)
        $globalSearch = trim($this->request->query->all('search')['value'] ?? '');
        if ($globalSearch !== '' && empty($columnCriteria)) {
            $columnCriteria = [
                'publication'   => ['operator' => 'cn', 'value' => $globalSearch],
                'place'         => ['operator' => 'cn', 'value' => $globalSearch],
                'title'         => ['operator' => 'cn', 'value' => $globalSearch],
            ];
            $operator = 'or';
        }

        $criteria = array_merge($baseCriteria, $columnCriteria);

        $search = [
            'criteria'       => $criteria,
            'operator'       => $operator,
            'skip'           => $start,
            'max'            => $length,
            'mentionedDates' => $sessionSearch['mentionedDates'] ?? 'without',
        ];

        try {
            $result = $this->xmlService->searchPaginated($search, $sort);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'draw'            => $draw,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Server error: ' . $e->getMessage(),
            ]);
        }

        $data = [];
        foreach ($result['data'] as $record) {
            $raw    = $record->toArray();
            $dates  = $raw['dates'] ?? [];
            $provenances = $raw['provenances'] ?? [];
            $data[] = [
                'hgvId'          => (string)($raw['id']          ?? ''),
                'tm'             => (string)($raw['tm']          ?? ''),
                'publ'           => (string)($raw['publ']        ?? ''),
                'dating'         => (string)($raw['dating']      ?? ''),
                'place'          => (string)($raw['place']       ?? ''),
                'title'          => (string)($raw['title']       ?? ''),
                'material'       => (string)($raw['material']    ?? ''),
                'keywords'       => (string)($raw['keywords']    ?? ''),
                'otherPub'       => (string)($raw['otherPub']    ?? ''),
                'DT_RowId'       => 'row_' . ($raw['id'] ?? ''),
                // hidden columns
                'ddb'            => (string)($raw['ddb']         ?? ''),
                'pubAbbr'        => (string)($raw['pubAbbr']     ?? ''),
                'pubVol'         => (string)($raw['pubVol']      ?? ''),
                'pubNr'          => (string)($raw['pubNr']       ?? ''),
                'notBefore'      => (string)($raw['notBefore']   ?? ''),
                'notAfter'       => (string)($raw['notAfter']    ?? ''),
                'when'           => (string)($raw['when']        ?? ''),
                'precision'      => (string)($raw['precision']   ?? ''),
                'settlement'     => (string)($raw['settlement']  ?? ''),
                'collection'     => (string)($raw['collection']  ?? ''),
                'invNo'          => (string)($raw['invNo']       ?? ''),
                'provenance'     => (string)($raw['provenance']  ?? ''),
                'provenancePlace' => (string)($raw['provenancePlace'] ?? ''),
                'provenanceNome'  => (string)($raw['provenanceNome']  ?? ''),
                'illustrations'  => (string)($raw['illustrations'] ?? ''),
                'figureUrls'     => (string)($raw['figureUrls']  ?? ''),
                'translations'   => (string)($raw['translations'] ?? ''),
                'commentary'     => (string)($raw['commentary']  ?? ''),
                'mentionedDates' => (string)($raw['mentionedDatesText'] ?? ''),
                'blOnline'       => (string)($raw['blOnline'] ?? ''),
                'dates'          => $dates,
                'provenances'    => $provenances,
            ];
        }

        return new JsonResponse([
            'draw'            => $draw,
            'recordsTotal'    => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data'            => $data,
        ]);
    }

    // ── Parameter parsing helpers (unchanged from original BrowseController) ─────

    protected function getParameterShow(): array
    {
        if ($show = $this->getParameter('show')) {
            return $show;
        }
        if ($show = $this->getSessionParameter('show')) {
            return $show;
        }
        return ['skip' => 0, 'max' => 1];
    }

    protected function getParameterSearch(): array
    {
        if ($search = $this->getParameter('search')) {
            // Remove empty criteria
            if (isset($search['criteria'])) {
                foreach ($search['criteria'] as $key => $criterion) {
                    $value = trim($criterion['value'] ?? '');
                    if ($value !== '') {
                        $search['criteria'][$key]['value'] = $value;
                    } else {
                        unset($search['criteria'][$key]);
                    }
                }
            } else {
                $search['criteria'] = [];
            }

            if (!isset($search['operator']) || !in_array($search['operator'], ['and', 'or'], true)) {
                $search['operator'] = 'and';
            }
            if (!isset($search['mentionedDates']) || !in_array($search['mentionedDates'], ['with', 'without', 'only'], true)) {
                $search['mentionedDates'] = 'without';
            }
            if (!isset($search['skip']) || !is_numeric($search['skip'])) {
                $search['skip'] = 0;
            }
            if (!isset($search['max']) || !is_numeric($search['max'])) {
                $search['max'] = 100;
            }

            return $search;
        }

        if ($search = $this->getSessionParameter('search')) {
            return $search;
        }

        return ['criteria' => [], 'operator' => 'and', 'skip' => 0, 'max' => 100, 'mentionedDates' => 'without'];
    }

    protected function getParameterSort(): array
    {
        if ($sortList = $this->getParameter('sort')) {
            $final = [];
            $idx   = 1;
            foreach ($sortList as $sort) {
                $key = $sort['key'] ?? '';
                if ($key === 'Datierung2') {
                    $final[$idx++] = ['key' => 'sortYear', 'direction' => $sort['direction']];
                } elseif ($key === 'PublikationL') {
                    $final[$idx++] = ['key' => 'publication', 'direction' => $sort['direction']];
                } elseif ($key !== '') {
                    $final[$idx++] = $sort;
                }
            }
            if (!empty($final)) {
                return $final;
            }
        }

        if ($sortList = $this->getSessionParameter('sort')) {
            return $sortList;
        }

        return [1 => ['key' => 'sortYear', 'direction' => 'ascend']];
    }
}
