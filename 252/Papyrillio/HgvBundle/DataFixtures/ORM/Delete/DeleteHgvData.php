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
 * 1.) Export data records from FileMaker to src/Papyrillio/HgvBundle/Data/hgvDelete.csv (or simply collect them by hand)
 * 2.) Run Delete-Skript
 * php app/console doctrine:fixtures:load --fixtures=src/Papyrillio/HgvBundle/DataFixtures/ORM/Delete --append
 * */

class DeleteHgvData  extends AbstractFixture implements OrderedFixtureInterface
{

    function load(ObjectManager $manager){

      if(($handle = fopen(__DIR__ . '/../../../Data/' . 'hgvDelete.csv', 'r')) !== FALSE) {
        while(($data = fgetcsv($handle, 10)) !== FALSE) {

          $hgv = $manager->getRepository('PapyrillioHgvBundle:Hgv')->findOneBy(array('id' => $data[0]));
          
          echo 'Deleting ' . $hgv . "\n";
          
          $manager->remove($hgv);
        }
        fclose($handle);
      }

      $manager->flush();
    }

    public function getOrder(){
      return 3;
    }
}
?>