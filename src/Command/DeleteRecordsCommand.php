<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\UnitOfWork;
use App\Entity\Hgv;
use App\Entity\PictureLink;

use Doctrine\ORM\EntityManagerInterface;

class DeleteRecordsCommand extends Command
{
    // the name of the command (the part after "bin/console")
	const IMPORT_DIR          = __DIR__ . '/../../data/';

	protected static $defaultName = 'app:delete-records';
	protected $entityManager;
	protected $importFile = 'hgvDelete.csv';

	public function __construct(EntityManagerInterface $entityManager){
		$this->entityManager = $entityManager;
		parent::__construct();
    }

    protected function configure(): void
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

      if(($handle = fopen(self::IMPORT_DIR . '/' . $this->importFile, 'r')) !== FALSE) {
	      while(($data = fgetcsv($handle, 10)) !== FALSE) {
		      $hgvId = $data[0];
		      if(preg_match('/^\d+[a-z]*( [XYZ])?$/', $hgvId)){

          $hgv = $this->entityManager->getRepository(Hgv::class)->findOneBy(array('id' => $hgvId));
          
          echo 'Deleting ' . $hgv . "\n";
          
	  $this->entityManager->remove($hgv);
		      }
        }
        fclose($handle);
      }

      $this->entityManager->flush();

        return Command::SUCCESS;
        // return Command::FAILURE;
        // return Command::INVALID
    }
}
