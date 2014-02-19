<?php
namespace Papyrillio\HgvBundle\DataFixtures\ORM\Update;

use Papyrillio\HgvBundle\DataFixtures\ORM\XmlData;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\UnitOfWork;
use Papyrillio\HgvBundle\Entity\Hgv;
use DateTime;
use DOMDocument;
use DOMXPath;

/*
 * 1.) Export data records from FileMaker to src/Papyrillio/HgvBundle/Data/hgvUpdate.xml
 * 2.) Run Update-Skript
 * php app/console doctrine:fixtures:load --fixtures=src/Papyrillio/HgvBundle/DataFixtures/ORM/Update --append
 * */

class LoadHgvData extends XmlData
{
    function __construct(){
      parent::__construct('hgvUpdate.xml');
    }

    function load(ObjectManager $manager)
    {
      foreach($this->xpath->evaluate('/fm:FMPXMLRESULT/fm:RESULTSET[1]/fm:ROW') as $row){

        $hgv = $manager->getRepository('PapyrillioHgvBundle:Hgv')->findOneBy(array('id' => $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row)->item($this->positions['texIdLang'])->nodeValue));

        //if($hgv && $hgv->getPictureLinks()){
        //  foreach($hgv->getPictureLinks() as $pictureLink){
         //   $manager->remove($pictureLink);
         // }
         // $hgv->resetPictureLinks();
       // }

        $hgv = $this->generateObjectFromXml($row, $hgv);

        if($manager->getUnitOfWork()->getEntityState($hgv) === UnitOfWork::STATE_NEW){
          $manager->persist($hgv);
        }

        //foreach($hgv->getPictureLinks() as $pictureLink){
       //   $manager->persist($pictureLink);
       // }

        echo $this->flushCounter . ': ' . $hgv->getPublikationLang() . ' (#' . $hgv->getId() . ")\n";

        if(($this->flushCounter++ % 400) === 0){
          $manager->flush();
          $manager->clear();
        }
      }
      $manager->flush();
      $manager->clear();
    }

    public function getOrder(){
      return 3;
    }
}
?>