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
        'publikation'       => 'Publikation',
        'band'              => 'Band',
        'nummer'            => 'Nummer',
        'tmNr'              => 'TM Nr.',
        'jahr'              => 'Jahr',
        'jh'                => 'Jahrhundert',
        'ort'               => 'Ort',
        'originaltitel'     => 'Originaltitel',
        'material'          => 'Material',
        'abbildung'         => 'Abbildung',
        'anderePublikation' => 'Andere Publikation',
        'bemerkungen'       => 'Bemerkungen',
        'inhalt'            => 'Inhalt',
        'url'               => 'Link',
        'chronMinimum'      => 'Chron-Minimum',
        'chronMaximum'      => 'Chron-Maximum',
        'chronGlobal'       => 'Chron-Global',
        'uebersetzungen'    => 'Übersetzungen',
    ];

    /** Used for the single-record field labels. */
    static $FIELD_LIST_SINGLE = [
        'publikationLang'   => 'Publikation',
        'datierungIi'       => 'Datierung',
        'ort'               => 'Ort',
        'originaltitelHtml' => 'Originaltitel',
        'material'          => 'Material',
        'abbildung'         => 'Abbildung',
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

        // ── Column index → sort key map ───────────────────────────────────────────
        // Must match browseMulti.js column definitions (0-based)
        $sortableColumns = [
            0 => null,               // hgvId link (row number)
            1 => 'publikationLang',
            2 => 'datierungIi',
            3 => 'ort',
            4 => 'originaltitelHtml',
            5 => 'material',
            6 => 'inhalt',           // keywords
            7 => 'anderePublikation',
            8 => 'tmNr',
        ];

        // ── Column index → search field map ──────────────────────────────────────
        $searchableColumns = [
            1 => 'publikation',
            2 => 'datierungIi',
            3 => 'ort',
            4 => 'originaltitel',
            5 => 'material',
            6 => 'inhalt',
            7 => 'anderePublikation',
            8 => 'tmNr',
        ];

        // ── Build sort ────────────────────────────────────────────────────────────
        $sort = [];
        $idx  = 1;
        foreach ($this->request->query->all('order') as $orderItem) {
            $colIdx = (int)($orderItem['column'] ?? 0);
            $key    = $sortableColumns[$colIdx] ?? null;
            if ($key !== null) {
                $sort[$idx++] = [
                    'key'       => $key,
                    'direction' => ($orderItem['dir'] ?? 'asc') === 'desc' ? 'descend' : 'ascend',
                ];
            }
        }
        if (empty($sort)) {
            $sort = [1 => ['key' => 'chronGlobal', 'direction' => 'ascend']];
        }

        // ── Build per-column search criteria (override session) ───────────────────
        $columnCriteria = [];
        foreach ($this->request->query->all('columns') as $colIdx => $col) {
            $fieldName = $searchableColumns[(int)$colIdx] ?? null;
            $val       = trim($col['search']['value'] ?? '');
            if ($fieldName !== null && $val !== '') {
                $columnCriteria[$fieldName] = ['operator' => 'cn', 'value' => $val];
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
                'publikation'   => ['operator' => 'cn', 'value' => $globalSearch],
                'ort'           => ['operator' => 'cn', 'value' => $globalSearch],
                'originaltitel' => ['operator' => 'cn', 'value' => $globalSearch],
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

        $result  = $this->xmlService->searchPaginated($search, $sort);

        $data = [];
        foreach ($result['data'] as $record) {
            $data[] = [
                'hgvId'            => (string)$record->getHgvId(),
                'tm'               => (string)$record->getTmNr(),
                'publ'             => (string)$record->getPublikationLang(),
                'dating'           => (string)$record->getDatierungIi(),
                'place'            => (string)$record->getOrt(),
                'title'            => (string)$record->getOriginaltitelHtml(),
                'material'         => (string)$record->getMaterial(),
                'keywords'         => (string)$record->getInhalt(),
                'otherPub'         => (string)$record->getAnderePublikation(),
                'DT_RowId'         => 'row_' . $record->getId(),
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
                    $final[$idx++] = ['key' => 'chronGlobal', 'direction' => $sort['direction']];
                } elseif ($key === 'PublikationL') {
                    $final[$idx++] = ['key' => 'publikationLang', 'direction' => $sort['direction']];
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

        return [1 => ['key' => 'chronGlobal', 'direction' => 'ascend']];
    }
}
