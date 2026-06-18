<?php

namespace App\Service;

/**
 * Translates HGV keyword terms from German into French, English, Spanish and Italian.
 *
 * The translation dictionary is loaded from data/keywords.csv, which maps German terms
 * (and a handful of special multi-word phrases) to their equivalents in four languages.
 *
 * The regular keyword format is:
 *
 *   keyword = word [(' und '|' oder ') word] ['(' addword (',' addword)* ')']
 *   word    = <phrase from CSV dictionary>
 *   addword = <phrase from CSV dictionary>
 *
 * Question-mark uncertainty markers ("(?)", "?, ", trailing "?") are stripped
 * by normalise() before any lookup or parsing takes place.
 *
 * The parser first tries an exact dictionary look-up on the normalised form;
 * if that fails it decomposes the keyword into its structural components.
 * Unrecognised components (proper names, free-text phrases, …) cause the
 * translation for that language to be omitted rather than producing misleading output.
 */
class KeywordTranslator
{
    public const LANGUAGES = ['fr', 'en', 'es', 'it'];

    /** Language-specific equivalents of the German logical connectors. */
    private const CONNECTORS = [
        'und'  => ['fr' => 'et',  'en' => 'and', 'es' => 'y',  'it' => 'e'],
        'oder' => ['fr' => 'ou',  'en' => 'or',  'es' => 'o',  'it' => 'o'],
    ];

    /** @var array<string, array{fr:string,en:string,es:string,it:string}> */
    private array $dict = [];

    /** @var string[]  Dictionary keys sorted longest-first for greedy prefix matching. */
    private array $sortedKeys = [];

    public function __construct(private readonly string $csvPath)
    {
        $this->loadCsv();
    }

    // ── Public API ────────────────────────────────────────────────────────────────

    /**
     * Translate a German HGV keyword term into all four target languages.
     *
     * Returns an array keyed by language code ('fr', 'en', 'es', 'it').
     * A null value means no translation could be determined for that language.
     *
     * @return array<string, string|null>
     */
    public function translateAll(string $de): array
    {
        $de = $this->normalise(trim($de));

        // 1. Exact dictionary match (highest priority; covers atomic multi-word entries)
        if (isset($this->dict[$de])) {
            return $this->normaliseLangMap($this->dict[$de]);
        }

        // 2. Structural decomposition
        $parsed = $this->parse($de);
        if ($parsed === null) {
            return array_fill_keys(self::LANGUAGES, null);
        }

        $result = [];
        foreach (self::LANGUAGES as $lang) {
            $result[$lang] = $this->assemble($parsed, $lang);
        }
        return $result;
    }

    // ── Normaliser ────────────────────────────────────────────────────────────────

    /**
     * Strip all question-mark uncertainty markers from a raw keyword term.
     *
     *   " (?)"       → removed entirely (uncertain addendum with no content)
     *   "?, "        → removed from the start of an addendum
     *   trailing "?" → removed from each comma-separated addendum item
     */
    private function normalise(string $keyword): string
    {
        // Remove " (?)" — a standalone uncertainty addendum
        $keyword = preg_replace('/\s*\(\?\)/', '', $keyword);

        // Process remaining addenda: strip leading "?, " and trailing "?" on each item
        $keyword = preg_replace_callback(
            '/\(([^)]+)\)/',
            static function (array $m): string {
                $inner = $m[1];
                // Strip optional leading "?, "
                $inner = preg_replace('/^\?\s*,\s*/', '', $inner);
                // Strip trailing "?" from each comma-separated item
                $parts = array_map(
                    static fn(string $p): string => rtrim(trim($p), '?'),
                    explode(',', $inner)
                );
                $parts = array_filter($parts, static fn(string $p): bool => $p !== '');
                return empty($parts) ? '' : '(' . implode(', ', $parts) . ')';
            },
            $keyword
        );

        return trim($keyword);
    }

    // ── Parser ────────────────────────────────────────────────────────────────────

    /**
     * Decompose a (already-normalised) keyword into its structural components.
     *
     * Returns an associative array:
     *   ['word'=>string, 'connector'=>string|null, 'word2'=>string|null, 'addendum'=>string|null]
     * or null when the input cannot be fully explained by the grammar.
     */
    private function parse(string $keyword): ?array
    {
        $s = trim($keyword);

        $components = [
            'word'      => null,
            'connector' => null,
            'word2'     => null,
            'addendum'  => null,
        ];

        // Strip trailing parenthetical addendum: "word (addendum)"
        // Non-greedy so "(a) (b)" is rejected (only one addendum allowed).
        if (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/su', $s, $m)) {
            $s                      = rtrim($m[1]);
            $components['addendum'] = $m[2];
        }

        // Match the main word (greedy: longest dictionary entry at start of $s)
        [$word1, $after] = $this->matchAtStart($s);
        if ($word1 === null) {
            return null;
        }
        $components['word'] = $word1;
        $after = ltrim($after);

        // Keyword fully consumed → done
        if ($after === '') {
            return $components;
        }

        // Try optional "und|oder" + second word, which must consume the rest
        if (preg_match('/^(und|oder)\s+(.+)$/su', $after, $m)) {
            [$word2, $rest2] = $this->matchAtStart($m[2]);
            if ($word2 !== null && trim($rest2) === '') {
                $components['connector'] = $m[1];
                $components['word2']     = $word2;
                return $components;
            }
        }

        // Unaccounted remainder → keyword cannot be fully parsed
        return null;
    }

    /**
     * Find the longest dictionary entry that is a prefix of $s.
     *
     * The match is only accepted when the prefix is followed by end-of-string,
     * a space, or an opening parenthesis (start of an addendum).
     *
     * @return array{0: string|null, 1: string}  [matched_phrase_or_null, remainder]
     */
    private function matchAtStart(string $s): array
    {
        foreach ($this->sortedKeys as $phrase) {
            $plen = mb_strlen($phrase);
            if ($plen > mb_strlen($s)) {
                continue;
            }
            if (mb_substr($s, 0, $plen) !== $phrase) {
                continue;
            }
            $after = mb_substr($s, $plen);
            if ($after === '' || $after[0] === ' ' || $after[0] === '(') {
                return [$phrase, $after];
            }
        }
        return [null, $s];
    }

    // ── Translation assembler ─────────────────────────────────────────────────────

    /**
     * Build the translated keyword string from parsed components for one language.
     *
     * Returns null when the main word has no translation in that language.
     * If word2 or addendum components cannot be translated, they are either omitted
     * (word2) or kept in German (addendum) rather than causing a complete failure.
     */
    private function assemble(array $c, string $lang): ?string
    {
        // Main word is mandatory
        $wordTr = $this->lookupWord($c['word'], $lang);
        if ($wordTr === null) {
            return null;
        }

        $parts   = [$wordTr];

        // Optional connector + second word
        if ($c['connector'] !== null && $c['word2'] !== null) {
            $word2Tr = $this->lookupWord($c['word2'], $lang);
            if ($word2Tr !== null) {
                $parts[] = self::CONNECTORS[$c['connector']][$lang] ?? $c['connector'];
                $parts[] = $word2Tr;
            }
            // If word2 is untranslatable we silently omit the conjunction
        }

        $result = implode(' ', $parts);

        // Optional parenthetical addendum
        if ($c['addendum'] !== null) {
            $addTr   = $this->translateAddendum($c['addendum'], $lang);
            // Fallback: keep the original German text inside the parentheses
            $result .= ' (' . ($addTr ?? $c['addendum']) . ')';
        }

        return $result;
    }

    // ── Addendum translator ───────────────────────────────────────────────────────

    /**
     * Translate the content of a (already-normalised) keyword addendum.
     *
     * Addenda are comma-separated lists of plain words/phrases (no '?' markers).
     * Each item is looked up in the dictionary; items with inner 'und'/'oder'
     * connectors are also handled.
     *
     * Returns the translated addendum string, or null to signal the caller to
     * fall back to the original German addendum text.
     */
    private function translateAddendum(string $addendum, string $lang): ?string
    {
        $addendum = trim($addendum);
        if ($addendum === '') {
            return null;
        }

        $parts      = array_map('trim', explode(',', $addendum));
        $translated = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            // Inner connector: "Landsteuern und Kopfsteuern", "Pacht oder Miete"
            $handled = false;
            foreach (['und', 'oder'] as $conn) {
                if (preg_match('/^(.+)\s+' . $conn . '\s+(.+)$/su', $part, $m)) {
                    $t1 = $this->lookupWord(trim($m[1]), $lang);
                    $t2 = $this->lookupWord(trim($m[2]), $lang);
                    if ($t1 !== null && $t2 !== null) {
                        $translated[] = $t1 . ' ' . self::CONNECTORS[$conn][$lang] . ' ' . $t2;
                        $handled      = true;
                        break;
                    }
                }
            }
            if ($handled) {
                continue;
            }

            // Single word/phrase
            $tr = $this->lookupWord($part, $lang);
            if ($tr === null) {
                return null; // signal to caller: fall back to German addendum
            }
            $translated[] = $tr;
        }

        return $translated !== [] ? implode(', ', $translated) : null;
    }

    // ── Dictionary helpers ────────────────────────────────────────────────────────

    private function loadCsv(): void
    {
        if (!is_file($this->csvPath)) {
            return;
        }

        $fh     = fopen($this->csvPath, 'r');
        $header = null;

        while (($row = fgetcsv($fh)) !== false) {
            if ($header === null) {
                $header = $row;
                continue;
            }
            if (count($row) < 7) {
                continue;
            }
            $de = trim($row[2] ?? '');
            if ($de === '') {
                continue;
            }
            $this->dict[$de] = [
                'fr' => trim($row[3] ?? ''),
                'en' => trim($row[4] ?? ''),
                'es' => trim($row[5] ?? ''),
                'it' => trim($row[6] ?? ''),
            ];
        }

        fclose($fh);

        // Sort longest-first so greedy prefix matching prefers more specific entries
        $this->sortedKeys = array_keys($this->dict);
        usort($this->sortedKeys, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    }

    /** Look up a single German word/phrase and return the target-language string, or null. */
    private function lookupWord(string $de, string $lang): ?string
    {
        $v = $this->dict[$de][$lang] ?? '';
        return $v !== '' ? $v : null;
    }

    /**
     * Convert a raw dictionary entry (where empty string means "no translation")
     * into the canonical array used by translateAll().
     *
     * @param  array{fr:string,en:string,es:string,it:string} $entry
     * @return array<string, string|null>
     */
    private function normaliseLangMap(array $entry): array
    {
        $result = [];
        foreach (self::LANGUAGES as $lang) {
            $v            = $entry[$lang] ?? '';
            $result[$lang] = $v !== '' ? $v : null;
        }
        return $result;
    }
}
