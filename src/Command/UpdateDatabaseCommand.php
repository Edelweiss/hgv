<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\UnitOfWork;
use App\Command\ParseHgvFmpCommand;
use App\Entity\Hgv;
use App\Entity\PictureLink;

use Doctrine\ORM\EntityManagerInterface;

class UpdateDatabaseCommand extends ParseHgvFmpCommand
{
    // the name of the command (the part after "bin/console")
	protected static $defaultName = 'app:update-database';
	protected $entityManager;

	public function __construct(EntityManagerInterface $entityManager){
		$this->entityManager = $entityManager;
	$this->importFile = 'hgvUpdate.xml';    
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
        foreach($this->xpath->evaluate('/fm:FMPXMLRESULT/fm:RESULTSET[1]/fm:ROW') as $row){

        $hgv = $this->entityManager->getRepository(Hgv::class)->findOneBy(array('id' => $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row)->item($this->positions['texIdLang'])->nodeValue));

        //if($hgv && $hgv->getPictureLinks()){
        //  foreach($hgv->getPictureLinks() as $pictureLink){
         //   $manager->remove($pictureLink);
         // }
         // $hgv->resetPictureLinks();
       // }

        $hgv = $this->generateObjectFromXml($row, $hgv);

        echo ($this->flushCounter + 1) . ': ' . $hgv->getPublikationLang() . ' (TM-Nr. ' . $hgv->getId() . ') [' . $unitOfWorkStates[$this->entityManager->getUnitOfWork()->getEntityState($hgv)] .  ']'  . "\n";
        if($this->entityManager->getUnitOfWork()->getEntityState($hgv) === UnitOfWork::STATE_NEW){
          $this->entityManager->persist($hgv);
        }

        //foreach($hgv->getPictureLinks() as $pictureLink){
       //   $manager->persist($pictureLink);
       // }


        if(($this->flushCounter++ % 400) === 0){
   #   $this->entityManager->flush();
   #   $this->entityManager->clear();
	}
      }
   #   $this->entityManager->flush();
   #   $this->entityManager->clear();

        return Command::SUCCESS;
        // return Command::FAILURE;
        // return Command::INVALID
    }
}
