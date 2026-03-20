<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Symfony console command to create/rebuild the BaseX databases
 * from HGV_meta_EpiDoc and DDB_EpiDoc_XML sources.
 *
 * Usage:
 *   bin/console app:basex:create-db
 *   bin/console app:basex:create-db /absolute/path/to/idp.data
 *   bin/console app:basex:create-db --database=hgv   (only rebuild HGV)
 *   bin/console app:basex:create-db --database=ddb   (only rebuild DDB)
 */
class BaseXCreateDatabaseCommand extends Command
{
    protected static $defaultName = 'app:basex:create-db';
    protected static $defaultDescription = 'Create BaseX databases from HGV_meta_EpiDoc and DDB_EpiDoc_XML sources';

    private string $idpDataPath;
    private string $projectDir;

    public function __construct(string $projectDir)
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
                'Only create a specific database: "hgv" or "ddb"'
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

        if (!$idpDataPath || !is_dir($idpDataPath)) {
            $io->error('idp.data directory not found: ' . ($idpDataPath ?: $input->getArgument('idp-data-path')));
            return Command::FAILURE;
        }

        $onlyDb = $input->getOption('database');
        if ($onlyDb && !in_array($onlyDb, ['hgv', 'ddb'], true)) {
            $io->error('Invalid database option. Use "hgv" or "ddb".');
            return Command::FAILURE;
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

        // Verify
        $io->section('Verification');

        $verifyXQuery = <<<'XQUERY'
declare namespace tei = 'http://www.tei-c.org/ns/1.0';
let $hgv-count := count(db:get('hgv')//tei:TEI)
let $ddb-count := count(db:get('ddb')//tei:TEI)
return 'HGV documents: ' || $hgv-count || '&#10;'
    || 'DDB documents: ' || $ddb-count
XQUERY;

        $this->runBaseXCommand("XQUERY $verifyXQuery", $output);

        $io->success('BaseX database setup complete.');
        return Command::SUCCESS;
    }

    private function runBaseXCommand(string $command, OutputInterface $output): int
    {
        $process = new Process(['basex', '-c', $command]);
        $process->setTimeout(600); // 10 minutes for large imports
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->getExitCode();
    }
}
