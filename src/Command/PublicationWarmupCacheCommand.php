<?php

namespace App\Command;

use App\Service\PublicationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:publication:warmup-cache',
    description: 'Pre-build publication_index.json to avoid first-request timeout on /publication'
)]
class PublicationWarmupCacheCommand extends Command
{
    public function __construct(private readonly PublicationService $publicationService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force rebuild by removing the current publication cache file first'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Publication cache warmup');
        $io->text('Building publication index from DDbDP (+ Historical reprint aliases)…');

        $stats = $this->publicationService->warmCache((bool)$input->getOption('force'));
        $durationSeconds = $stats['durationMs'] / 1000;
        $durationHuman = sprintf('%dm %02ds', (int)floor($durationSeconds / 60), (int)round($durationSeconds % 60));

        $io->definitionList(
            ['Cache file' => $stats['cacheFile']],
            ['Size (bytes)' => (string)$stats['cacheSize']],
            ['Volumes' => (string)$stats['volumes']],
            ['numbersByVolume keys' => (string)$stats['numbersByVolume']],
            ['idsByTriple keys' => (string)$stats['idsByTriple']],
            ['Duration (ms)' => (string)$stats['durationMs']],
            ['Duration (human)' => $durationHuman]
        );

        if ($stats['volumes'] === 0) {
            $io->warning('Built cache is empty (0 volumes). Check IDP_DATA_PATH and corpora directories.');
        } else {
            $io->success('Publication cache warmed successfully.');
        }

        return Command::SUCCESS;
    }
}
