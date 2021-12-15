<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\UnitOfWork;
use App\Command\ReadFodsCommand;
use App\Entity\Hgv;
use App\Entity\PictureLink;

use Doctrine\ORM\EntityManagerInterface;

class UpdateRecordsCommand extends ReadFodsCommand
{
  // the name of the command (the part after "bin/console")
  protected static $defaultName = 'app:update-records';
  protected $entityManager;

  public function __construct(EntityManagerInterface $entityManager){
    $this->entityManager = $entityManager;
    $this->importFile = 'hgv.fods';    
    parent::__construct();
  }

  protected function configure(): void
  {
    // ...
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $unitOfWorkStates = [UnitOfWork::STATE_REMOVED => 'REMOVED', UnitOfWork::STATE_MANAGED => 'MANAGED', UnitOfWork::STATE_DETACHED => 'DETACHED', UnitOfWork::STATE_NEW => 'NEW']; 
    echo '[managed = ' . UnitOfWork::STATE_MANAGED . '; rm = ' . UnitOfWork::STATE_REMOVED . '; detached = ' . UnitOfWork::STATE_DETACHED . '; new = ' . UnitOfWork::STATE_NEW . ']' . "\n";

    foreach($this->xpath->evaluate('//table:table-row[position() > ' . $this->headerLine . ']') as $row){
      $tm = $this->getValue($row, 'tm_id');
      if(\preg_match('/^\d+$/', $tm)){
        $hgv = $this->entityManager->getRepository(Hgv::class)->findOneBy(['id' => $tm]);
        $hgv = $this->generateObjectFromXml($row, $hgv);
        echo ($this->flushCounter + 1) . ': ' . $hgv->getPublikationLang() . ' (HGV full ' . $hgv->getId()  . ') [' . $unitOfWorkStates[$this->entityManager->getUnitOfWork()->getEntityState($hgv)] .  ']'  . "\n";

      

/*
      

      

      
      if($this->entityManager->getUnitOfWork()->getEntityState($hgv) === UnitOfWork::STATE_NEW){
        $this->entityManager->persist($hgv);
      }

      if(($this->flushCounter++ % 400) === 0){
        $this->entityManager->flush();
        $this->entityManager->clear();
      }*/
      }
    }
    /*$this->entityManager->flush();
    $this->entityManager->clear();
*/
    return Command::SUCCESS;
    //return Command::FAILURE;
    //return Command::INVALID
  }
}
