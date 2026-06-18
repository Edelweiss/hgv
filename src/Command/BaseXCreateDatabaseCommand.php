<?php

namespace App\Command;

use App\Service\KeywordTranslator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Symfony console command to create/rebuild the BaseX databases
 * from HGV_meta_EpiDoc and DDB_EpiDoc_XML sources, and to build
 * a companion keyword-translations database.
 *
 * Usage:
 *   bin/console app:basex:create-db
 *   bin/console app:basex:create-db /absolute/path/to/idp.data
 *   bin/console app:basex:create-db --database=hgv       (only rebuild HGV)
 *   bin/console app:basex:create-db --database=ddb       (only rebuild DDB)
 *   bin/console app:basex:create-db --database=keywords  (only rebuild keyword translations)
 */
class BaseXCreateDatabaseCommand extends Command
{
    protected static $defaultName = 'app:basex:create-db';
    protected static $defaultDescription = 'Create BaseX databases from HGV_meta_EpiDoc and DDB_EpiDoc_XML sources';

    private string $idpDataPath;
    private string $projectDir;

    public function __construct(string $projectDir, private readonly KeywordTranslator $translator)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'idp-data-path',
                InputArgument::OPTIONAL,
                'Path to the idp.data directory (defaults to IDP_DATA_PATH from .env)'
            )
            ->addOption(
                'database',
                'd',
                InputOption::VALUE_REQUIRED,
                'Only create a specific database: "hgv", "ddb", or "keywords"'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Resolve idp.data path
        $idpDataPath = $input->getArgument('idp-data-path');
        if (!$idpDataPath) {
            $idpDataPath = $_ENV['IDP_DATA_PATH'] ?? 'ipd.data';
        }
        if (!str_starts_with($idpDataPath, '/')) {
            $idpDataPath = $this->projectDir . '/' . $idpDataPath;
        }
        $idpDataPath = realpath($idpDataPath);

        $onlyDb = $input->getOption('database');
        if ($onlyDb && !in_array($onlyDb, ['hgv', 'ddb', 'keywords'], true)) {
            $io->error('Invalid database option. Use "hgv", "ddb", or "keywords".');
            return Command::FAILURE;
        }

        // The idp.data path is only required when creating hgv or ddb
        if ($onlyDb !== 'keywords') {
            if (!$idpDataPath || !is_dir($idpDataPath)) {
                $io->error('idp.data directory not found: ' . ($idpDataPath ?: $input->getArgument('idp-data-path')));
                return Command::FAILURE;
            }
        }

        $io->title('BaseX Database Setup');

        // Create HGV database
        if (!$onlyDb || $onlyDb === 'hgv') {
            $hgvDir = $idpDataPath . '/HGV_meta_EpiDoc';
            if (!is_dir($hgvDir)) {
                $io->error("HGV_meta_EpiDoc directory not found at: $hgvDir");
                return Command::FAILURE;
            }

            $io->section('Creating HGV database');
            $io->text("Source: $hgvDir");

            $result = $this->runBaseXCommand(
                "SET INTPARSE true\nSET DTD false\nSET STRIPWS false\n" .
                "DROP DB hgv\nCREATE DB hgv $hgvDir\nOPTIMIZE ALL\nCREATE INDEX fulltext\nINFO DB",
                $output
            );

            if ($result !== 0) {
                $io->error('Failed to create HGV database.');
                return Command::FAILURE;
            }
            $io->success('HGV database created.');
        }

        // Create DDB database
        if (!$onlyDb || $onlyDb === 'ddb') {
            $ddbDir = $idpDataPath . '/DDB_EpiDoc_XML';
            if (!is_dir($ddbDir)) {
                $io->error("DDB_EpiDoc_XML directory not found at: $ddbDir");
                return Command::FAILURE;
            }

            $io->section('Creating DDB database');
            $io->text("Source: $ddbDir");

            $result = $this->runBaseXCommand(
                "SET INTPARSE true\nSET DTD false\nSET STRIPWS false\n" .
                "DROP DB ddb\nCREATE DB ddb $ddbDir\nOPTIMIZE ALL\nCREATE INDEX fulltext\nINFO DB",
                $output
            );

            if ($result !== 0) {
                $io->error('Failed to create DDB database.');
                return Command::FAILURE;
            }
            $io->success('DDB database created.');
        }

        // Create keyword-translations database
        if (!$onlyDb || $onlyDb === 'keywords') {
            $result = $this->createKeywordsDatabase($io, $output);
            if ($result !== 0) {
                return Command::FAILURE;
            }
        }

        // Verify
        $io->section('Verification');

        $verifyXQuery = <<<'XQUERY'
declare namespace tei = 'http://www.tei-c.org/ns/1.0';
let $hgv-count  := count(db:get('hgv')/tei:TEI)
let $ddb-count  := count(db:get('ddb')/tei:TEI)
let $kw-count   := count(db:get('keywords')//kw)
return 'HGV documents: ' || $hgv-count  || '&#10;'
    || 'DDB documents: ' || $ddb-count  || '&#10;'
    || 'Keyword translations: ' || $kw-count
XQUERY;

        $this->runBaseXCommand('XQUERY ' . self::flattenXQuery($verifyXQuery), $output);

        $io->success('BaseX database setup complete.');
        return Command::SUCCESS;
    }

    // ── Keyword-translations database ─────────────────────────────────────────────

    /**
     * Build the `keywords` BaseX database.
     *
     * Steps:
     *  1. Query all distinct keyword terms from the existing `hgv` database.
     *  2. Translate each term using KeywordTranslator (EBNF parser + CSV dictionary).
     *  3. Serialise results to an XML document.
     *  4. Write the document to a temp file and import it into BaseX.
     *
     * The resulting `keywords` database supports efficient attribute-value lookups:
     *   db:get('keywords')//kw[@de = 'Brief (privat)']/@en/string()
     */
    private function createKeywordsDatabase(SymfonyStyle $io, OutputInterface $output): int
    {
        $io->section('Creating keyword-translations database');

        // 1. Query all distinct keyword terms from the HGV database
        $io->text('Querying distinct keyword terms from the HGV database…');

        $collectXQuery = <<<'XQ'
declare namespace tei = 'http://www.tei-c.org/ns/1.0';
declare option output:method 'text';
string-join(
  distinct-values(db:get('hgv')//tei:keywords[@scheme='hgv']/tei:term/text()),
  '&#10;'
)
XQ;

        $raw = $this->captureBaseXCommand('XQUERY ' . self::flattenXQuery($collectXQuery), $output);
        $keywords = array_filter(array_map('trim', explode("\n", $raw)));

        if (empty($keywords)) {
            $io->warning('No keyword terms found in the HGV database – keywords database will be empty.');
        }

        $io->text(sprintf('Found %d distinct keyword terms.', count($keywords)));

        // 2. Translate each term
        $io->text('Translating keywords using CSV dictionary and EBNF parser…');

        $generated   = (new \DateTimeImmutable())->format('Y-m-d');
        $xmlLines    = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<!-- Keyword translations for the HGV database',
            '     Generated: ' . $generated,
            '     Each <kw> element carries the original German term in @de and',
            '     translation attributes for available languages (fr, en, es, it).',
            '     Usage: db:get(\'keywords\')//kw[@de = $term]/@en/string() -->',
            '<keywords generated="' . $generated . '">',
        ];

        $translatedCount = 0;
        $partialCount    = 0;

        foreach ($keywords as $de) {
            $translations = $this->translator->translateAll($de);

            // Skip entries that have no translation in any language
            $hasAny = array_filter($translations, static fn($v) => $v !== null);
            if (empty($hasAny)) {
                continue;
            }

            $attrs = 'de="' . htmlspecialchars($de, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"';
            $langCount = 0;
            foreach (KeywordTranslator::LANGUAGES as $lang) {
                if ($translations[$lang] !== null) {
                    $attrs .= ' ' . $lang . '="' . htmlspecialchars($translations[$lang], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"';
                    ++$langCount;
                }
            }

            $xmlLines[] = '  <kw ' . $attrs . '/>';
            ++$translatedCount;
            if ($langCount < count(KeywordTranslator::LANGUAGES)) {
                ++$partialCount;
            }
        }

        $xmlLines[] = '</keywords>';

        $io->text(sprintf(
            'Produced translations for %d/%d keywords (%d with all four languages, %d partial).',
            $translatedCount,
            count($keywords),
            $translatedCount - $partialCount,
            $partialCount
        ));

        // 3. Write XML to a temporary file
        $tmpFile = sys_get_temp_dir() . '/hgv_keywords_' . date('Ymd_His') . '.xml';
        file_put_contents($tmpFile, implode("\n", $xmlLines) . "\n");

        // 4. Import into BaseX as the `keywords` database
        $result = $this->runBaseXCommand(
            "DROP DB keywords\nCREATE DB keywords $tmpFile\nOPTIMIZE ALL\nCREATE INDEX attribute\nINFO DB",
            $output
        );

        if ($result !== 0) {
            $io->error('Failed to create keywords database. Generated XML kept for inspection: ' . $tmpFile);
            return $result;
        }

        @unlink($tmpFile);

        $io->success('Keywords database created.');
        return 0;
    }

    // ── BaseX process helpers ─────────────────────────────────────────────────────

    /**
     * Run a BaseX command string, printing all output to the console.
     * Returns the process exit code.
     */
    private function runBaseXCommand(string $command, OutputInterface $output): int
    {
        $process = new Process(['basex', '-c', $command]);
        $process->setTimeout(600); // 10 minutes for large imports
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->getExitCode();
    }

    /**
     * Run a BaseX command and return its stdout as a string.
     * Stderr is still forwarded to the console output.
     */
    private function captureBaseXCommand(string $command, OutputInterface $output): string
    {
        $process  = new Process(['basex', '-c', $command]);
        $process->setTimeout(300);
        $captured = '';

        $process->run(function ($type, $buffer) use ($output, &$captured) {
            if ($type === Process::OUT) {
                $captured .= $buffer;
            } else {
                $output->write($buffer); // forward stderr to console
            }
        });

        return $captured;
    }

    /**
     * Collapse a multi-line XQuery into a single line so it can be passed as one
     * argument to `basex -c "XQUERY …"`. The BaseX CLI parses each newline as a
     * separate command, so an XQuery split across lines is interpreted as
     * "XQUERY <first line>" followed by unknown commands like `declare` or `let`.
     */
    private static function flattenXQuery(string $xquery): string
    {
        return trim(preg_replace('/\s+/', ' ', $xquery));
    }
}
