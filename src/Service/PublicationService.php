<?php

namespace App\Service;

use App\Dto\Publication;

/**
 * Service that builds publication browse lists from DDB_EpiDoc_XML files.
 *
 * It distinguishes stubs (files with reprint-in references) from full files.
 * Stub entries are shown as "SOURCE -> TARGET" and resolve to TARGET records.
 */
class PublicationService
{
    private const TEI_NS = 'http://www.tei-c.org/ns/1.0';

    /** @var array<string,mixed>|null */
    private ?array $index = null;

    private string $cacheFile;
    private string $ddbRoot;
    private string $hgvRoot;
    private string $rdfCollectionFile;

    public function __construct()
    {
        $paths = $this->resolveDataPaths();
        $this->ddbRoot = $paths['ddbRoot'];
        $this->hgvRoot = $paths['hgvRoot'];
        $this->rdfCollectionFile = $paths['rdfCollectionFile'];
        $this->cacheFile = dirname(__DIR__, 2) . '/var/cache/publication_index.json';
    }

    /**
     * Get all distinct (series, volume) tuples, ordered by series then volume.
     *
     * @return Publication[]
     */
    public function getVolumes(): array
    {
        $index = $this->loadIndex();
        $publications = [];
        foreach ($index['volumes'] as $row) {
            $publications[] = new Publication(
                (string)$row['series'],
                (string)$row['volume'],
                '',
                (string)$row['volumeLabel']
            );
        }
        return $publications;
    }

    /**
     * Get all distinct publication numbers for a given (series, volume) pair.
     *
     * @return Publication[]
     */
    public function getNumbers(string $series, string $volume): array
    {
        $index = $this->loadIndex();
        $volumeKey = $this->tupleKey([$series, $volume, '']);
        $rows = $index['numbersByVolume'][$volumeKey] ?? [];

        $publications = [];
        foreach ($rows as $row) {
            $publications[] = new Publication(
                $series,
                $volume,
                (string)$row['number'],
                '',
                (string)$row['label'],
                (string)$row['targetSeries'],
                (string)$row['targetVolume'],
                (string)$row['targetNumber']
            );
        }
        return $publications;
    }

    /**
     * Find HGV record IDs matching a specific (series, volume, number) triple.
     *
     * @return string[]
     */
    public function findRecordIds(string $series, string $volume, string $number): array
    {
        $index = $this->loadIndex();
        $key = $this->tupleKey([$series, $volume, $number]);
        return $index['idsByTriple'][$key] ?? [];
    }

    /** @return array{ddbRoot:string,hgvRoot:string,rdfCollectionFile:string} */
    private function resolveDataPaths(): array
    {
        $projectDir = dirname(__DIR__, 2);
        $idpPath = $_ENV['IDP_DATA_PATH'] ?? getenv('IDP_DATA_PATH') ?: 'idp.data';

        $candidates = [];
        $candidates[] = $idpPath;
        $candidates[] = $projectDir . '/' . ltrim($idpPath, '/');
        $candidates[] = dirname($projectDir) . '/' . ltrim($idpPath, '/');
        $candidates[] = dirname(dirname($projectDir)) . '/data/idp.data';
        $candidates[] = dirname(dirname(dirname($projectDir))) . '/data/idp.data';

        foreach ($candidates as $base) {
            if (!$base) {
                continue;
            }

            $normalized = rtrim($base, '/');
            $roots = [
                $normalized,
                $normalized . '/master',
                $normalized . '/papyri/master',
            ];

            foreach ($roots as $root) {
                $ddbRoot = $root . '/DDB_EpiDoc_XML';
                $hgvRoot = $root . '/HGV_meta_EpiDoc';
                $rdfFile = $root . '/RDF/collection.rdf';
                if (is_dir($ddbRoot)) {
                    return [
                        'ddbRoot' => $ddbRoot,
                        'hgvRoot' => is_dir($hgvRoot) ? $hgvRoot : '',
                        'rdfCollectionFile' => is_file($rdfFile) ? $rdfFile : '',
                    ];
                }
            }
        }

        return ['ddbRoot' => '', 'hgvRoot' => '', 'rdfCollectionFile' => ''];
    }

    /** @return array<string,mixed> */
    private function loadIndex(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        if ($this->ddbRoot === '' || !is_dir($this->ddbRoot)) {
            $this->index = ['volumes' => [], 'numbersByVolume' => [], 'idsByTriple' => []];
            return $this->index;
        }

        $cacheTtlSeconds = 86400;
        if (is_file($this->cacheFile) && (time() - filemtime($this->cacheFile) < $cacheTtlSeconds)) {
            $json = file_get_contents($this->cacheFile);
            $cached = json_decode((string)$json, true);
            if (is_array($cached) && isset($cached['volumes'], $cached['numbersByVolume'], $cached['idsByTriple'])) {
                $this->index = $cached;
                return $this->index;
            }
        }

        $seriesPrettyMap = $this->loadSeriesPrettyMap();
        $rawEntries = [];
        $prettyHints = [];
        $idsByTriple = [];
        $hgvByTriple = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->ddbRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'xml') {
                continue;
            }

            $parsed = $this->parseDdbFile($file->getPathname());
            if ($parsed === null) {
                continue;
            }

            [$series, $volume, $number] = $parsed['tuple'];
            if ($series === '' || $number === '') {
                continue;
            }

            $sourceKey = $this->tupleKey($parsed['tuple']);

            foreach ($parsed['prettyHints'] as $tupleKey => $label) {
                if ($label !== '') {
                    $prettyHints[$tupleKey] = $label;
                }
            }

            if ($parsed['hgvId'] !== '') {
                $idsByTriple[$sourceKey] = $idsByTriple[$sourceKey] ?? [];
                if (!in_array($parsed['hgvId'], $idsByTriple[$sourceKey], true)) {
                    $idsByTriple[$sourceKey][] = $parsed['hgvId'];
                }
                if (!isset($hgvByTriple[$sourceKey])) {
                    $hgvByTriple[$sourceKey] = $parsed['hgvId'];
                }
            }

            $targetTuple = $parsed['stubTarget'] ?? $parsed['tuple'];

            $rawEntries[] = [
                'series' => $series,
                'volume' => $volume,
                'number' => $number,
                'targetSeries' => $targetTuple[0],
                'targetVolume' => $targetTuple[1],
                'targetNumber' => $targetTuple[2],
                'isStub' => $parsed['stubTarget'] !== null,
            ];
        }

        $numbersByVolume = [];
        $seenEntry = [];
        foreach ($rawEntries as $entry) {
            $sourceTuple = [$entry['series'], $entry['volume'], $entry['number']];
            $targetTuple = [$entry['targetSeries'], $entry['targetVolume'], $entry['targetNumber']];

            $sourceKey = $this->tupleKey($sourceTuple);
            $targetKey = $this->tupleKey($targetTuple);
            $volumeKey = $this->tupleKey([$entry['series'], $entry['volume'], '']);

            $sourcePretty = $this->resolvePrettyName($sourceTuple, $prettyHints, $seriesPrettyMap, $hgvByTriple);
            $targetPretty = $this->resolvePrettyName($targetTuple, $prettyHints, $seriesPrettyMap, $hgvByTriple);

            $label = $sourcePretty;
            if ($entry['isStub'] && $sourceKey !== $targetKey) {
                $label = $sourcePretty . ' → ' . $targetPretty;
            }

            $dedupeKey = $volumeKey . '|' . $sourceKey . '|' . $targetKey;
            if (isset($seenEntry[$dedupeKey])) {
                continue;
            }
            $seenEntry[$dedupeKey] = true;

            $numbersByVolume[$volumeKey][] = [
                'number' => $entry['number'],
                'label' => $label,
                'targetSeries' => $entry['targetSeries'],
                'targetVolume' => $entry['targetVolume'],
                'targetNumber' => $entry['targetNumber'],
                'sortNumber' => $this->sortToken($entry['number']),
            ];
        }

        foreach ($numbersByVolume as &$rows) {
            usort($rows, function (array $a, array $b): int {
                return $a['sortNumber'] <=> $b['sortNumber'];
            });
            foreach ($rows as &$row) {
                unset($row['sortNumber']);
            }
            unset($row);
        }
        unset($rows);

        $volumes = [];
        foreach (array_keys($numbersByVolume) as $volumeKey) {
            [$series, $volume] = explode(';', $volumeKey . ';');
            $volumes[] = [
                'series' => $series,
                'volume' => $volume,
                'volumeLabel' => $this->resolvePrettyName([$series, $volume, ''], $prettyHints, $seriesPrettyMap, $hgvByTriple, false),
                'sortSeries' => strtolower($this->resolvePrettyName([$series, '', ''], $prettyHints, $seriesPrettyMap, $hgvByTriple, false)),
                'sortVolume' => $this->sortToken($volume),
            ];
        }

        usort($volumes, function (array $a, array $b): int {
            $bySeries = $a['sortSeries'] <=> $b['sortSeries'];
            if ($bySeries !== 0) {
                return $bySeries;
            }
            return $a['sortVolume'] <=> $b['sortVolume'];
        });

        foreach ($volumes as &$volume) {
            unset($volume['sortSeries'], $volume['sortVolume']);
        }
        unset($volume);

        // Resolve stub target IDs from target tuple where available.
        foreach ($rawEntries as $entry) {
            if (!$entry['isStub']) {
                continue;
            }
            $sourceKey = $this->tupleKey([$entry['series'], $entry['volume'], $entry['number']]);
            $targetKey = $this->tupleKey([$entry['targetSeries'], $entry['targetVolume'], $entry['targetNumber']]);
            if (!isset($idsByTriple[$targetKey]) && isset($idsByTriple[$sourceKey])) {
                $idsByTriple[$targetKey] = $idsByTriple[$sourceKey];
            }
        }

        $this->index = [
            'volumes' => $volumes,
            'numbersByVolume' => $numbersByVolume,
            'idsByTriple' => $idsByTriple,
        ];

        @file_put_contents($this->cacheFile, json_encode($this->index, JSON_UNESCAPED_SLASHES));
        return $this->index;
    }

    /**
     * @return array{tuple: array{0:string,1:string,2:string}, hgvId:string, stubTarget: array{0:string,1:string,2:string}|null, prettyHints: array<string,string>}|null
     */
    private function parseDdbFile(string $filePath): ?array
    {
        $xml = @simplexml_load_file($filePath);
        if (!$xml) {
            return null;
        }
        $xml->registerXPathNamespace('tei', self::TEI_NS);

        $hybrid = trim((string)($xml->xpath("//tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='ddb-hybrid'][1]")[0] ?? ''));
        $tuple = $this->parseTuple($hybrid);
        if ($tuple === null) {
            return null;
        }

        $hgvId = trim((string)($xml->xpath("//tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='HGV'][1]")[0] ?? ''));

        $prettyHints = [];
        $stubTarget = null;

        $stubRefs = $xml->xpath("//tei:text/tei:body/tei:head//tei:ref[@type='reprint-in']");
        if (is_array($stubRefs) && !empty($stubRefs)) {
            $first = $stubRefs[0];
            $targetTuple = $this->parseTuple((string)($first['n'] ?? ''));
            if ($targetTuple !== null) {
                $stubTarget = $targetTuple;
                $label = $this->normalizePrettyLabel((string)$first);
                if ($label !== '') {
                    $prettyHints[$this->tupleKey($targetTuple)] = $label;
                }
            }
        }

        $fromRefs = $xml->xpath("//tei:text/tei:body/tei:head//tei:ref[@type='reprint-from']");
        if (is_array($fromRefs)) {
            foreach ($fromRefs as $ref) {
                $fromTuple = $this->parseTuple((string)($ref['n'] ?? ''));
                if ($fromTuple === null) {
                    continue;
                }
                $label = $this->normalizePrettyLabel((string)$ref);
                if ($label !== '') {
                    $prettyHints[$this->tupleKey($fromTuple)] = $label;
                }
            }
        }

        return [
            'tuple' => $tuple,
            'hgvId' => $hgvId,
            'stubTarget' => $stubTarget,
            'prettyHints' => $prettyHints,
        ];
    }

    /** @return array<string,string> */
    private function loadSeriesPrettyMap(): array
    {
        if ($this->rdfCollectionFile === '' || !is_file($this->rdfCollectionFile)) {
            return [];
        }

        $xml = @simplexml_load_file($this->rdfCollectionFile);
        if (!$xml) {
            return [];
        }

        $xml->registerXPathNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $xml->registerXPathNamespace('dcterms', 'http://purl.org/dc/terms/');

        $map = [];
        $descriptions = $xml->xpath('/rdf:RDF/rdf:Description');
        if (!is_array($descriptions)) {
            return [];
        }

        foreach ($descriptions as $desc) {
            $about = (string)($desc->attributes('rdf', true)['about'] ?? '');
            if ($about === '' || !str_contains($about, '/ddbdp/')) {
                continue;
            }
            $series = trim(substr($about, strrpos($about, '/') + 1));
            if ($series === '') {
                continue;
            }
            $citation = trim((string)($desc->children('dcterms', true)->bibliographicCitation ?? ''));
            if ($citation !== '') {
                $map[strtolower($series)] = $citation;
            }
        }
        return $map;
    }

    /** @param array{0:string,1:string,2:string} $tuple */
    private function tupleKey(array $tuple): string
    {
        return strtolower($tuple[0]) . ';' . $tuple[1] . ';' . $tuple[2];
    }

    /** @return array{0:string,1:string,2:string}|null */
    private function parseTuple(string $hybrid): ?array
    {
        $hybrid = trim($hybrid);
        if ($hybrid === '' || !str_contains($hybrid, ';')) {
            return null;
        }

        $parts = explode(';', $hybrid);
        $series = trim($parts[0] ?? '');
        $volume = trim($parts[1] ?? '');
        $number = trim($parts[2] ?? '');

        if ($series === '') {
            return null;
        }

        return [$series, $volume, $number];
    }

    /**
     * @param array{0:string,1:string,2:string} $tuple
     * @param array<string,string> $prettyHints
     * @param array<string,string> $seriesPrettyMap
     * @param array<string,string> $hgvByTriple
     */
    private function resolvePrettyName(array $tuple, array $prettyHints, array $seriesPrettyMap, array $hgvByTriple, bool $includeNumber = true): string
    {
        $key = $this->tupleKey($tuple);
        if (isset($prettyHints[$key]) && $prettyHints[$key] !== '') {
            return $this->normalizePrettyLabel($prettyHints[$key]);
        }

        if ($includeNumber) {
            $hgvPretty = $this->loadPrettyFromHgv($hgvByTriple[$key] ?? '');
            if ($hgvPretty !== '') {
                return $this->normalizePrettyLabel($hgvPretty);
            }
        }

        $seriesKey = strtolower($tuple[0]);
        $seriesLabel = $seriesPrettyMap[$seriesKey] ?? $tuple[0];
        $volume = $this->prettifyToken($tuple[1]);
        $number = $this->prettifyToken($tuple[2]);

        if ($includeNumber) {
            return $this->normalizePrettyLabel(trim(implode(' ', array_filter([$seriesLabel, $volume, $number], fn($v) => $v !== ''))));
        }
        return $this->normalizePrettyLabel(trim(implode(' ', array_filter([$seriesLabel, $volume], fn($v) => $v !== ''))));
    }

    private function loadPrettyFromHgv(string $hgvId): string
    {
        if ($hgvId === '' || $this->hgvRoot === '' || !is_dir($this->hgvRoot)) {
            return '';
        }

        $bucket = (int)floor(((int)$hgvId - 1) / 1000) + 1;
        $hgvFile = $this->hgvRoot . '/HGV' . $bucket . '/' . $hgvId . '.xml';
        if (!is_file($hgvFile)) {
            $fallback = glob($this->hgvRoot . '/HGV*/' . $hgvId . '.xml');
            if (!is_array($fallback) || !isset($fallback[0]) || !is_file($fallback[0])) {
                return '';
            }
            $hgvFile = $fallback[0];
        }

        $xml = @simplexml_load_file($hgvFile);
        if (!$xml) {
            return '';
        }

        $xml->registerXPathNamespace('tei', self::TEI_NS);
        $nodes = $xml->xpath("//tei:text/tei:body/tei:div[@type='bibliography'][@subtype='principalEdition']/tei:listBibl/tei:bibl[@type='publication'][@subtype='principal'][1]/*");
        if (!is_array($nodes) || empty($nodes)) {
            return '';
        }

        $parts = [];
        foreach ($nodes as $node) {
            $text = $this->normalizeWhitespace((string)$node);
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        return trim(implode(' ', $parts));
    }

    private function prettifyToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $token = preg_replace_callback('/_(\d+)/', function (array $m): string {
            return ' ' . $this->toRoman((int)$m[1]);
        }, $token) ?? $token;

        $token = str_replace('_', ' ', $token);
        $token = preg_replace('/(\d)([vr])\b/i', '$1 $2', $token) ?? $token;
        $token = preg_replace_callback('/\b([vr])\b/', fn(array $m): string => strtoupper($m[1]), $token) ?? $token;

        return trim($token);
    }

    private function toRoman(int $n): string
    {
        if ($n <= 0) {
            return (string)$n;
        }
        $map = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
            50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        $res = '';
        foreach ($map as $value => $roman) {
            while ($n >= $value) {
                $res .= $roman;
                $n -= $value;
            }
        }
        return $res;
    }

    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function normalizePrettyLabel(string $text): string
    {
        $text = $this->normalizeWhitespace($text);
        if ($text === '') {
            return '';
        }

        // Drop trailing qualifiers like "(col 2 only)" from reprint-from notes.
        $text = preg_replace('/\s*\([^)]*\)\s*$/u', '', $text) ?? $text;
        $text = preg_replace('/(\d)\.(\d)/', '$1 $2', $text) ?? $text;
        $text = preg_replace('/(\d),(\d)/', '$1 $2', $text) ?? $text;
        return $this->normalizeWhitespace($text);
    }

    private function sortToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '999999~';
        }

        if (preg_match('/^(\d+)(.*)$/', $value, $m)) {
            return sprintf('%08d', (int)$m[1]) . strtolower($m[2]);
        }

        return '999999~' . strtolower($value);
    }
}
