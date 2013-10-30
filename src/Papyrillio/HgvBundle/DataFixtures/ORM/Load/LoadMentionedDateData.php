<?php
namespace Papyrillio\HgvBundle\DataFixtures\ORM\Load;

use Papyrillio\HgvBundle\DataFixtures\ORM\XmlData;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Papyrillio\HgvBundle\Entity\MentionedDate;
use DateTime;
use DOMDocument;
use DOMXPath;

ini_set('memory_limit', -1);

class LoadMentionedDateData extends XmlData
{
    protected $fields = array(
      'zeile'          => 'Zeile',
      'jahr'           => 'J',
      'monat'          => 'M',
      'tag'            => 'T',
      'jh'             => 'Jh',
      'erg'            => 'Erg',
      'jahrIi'         => 'J2',
      'monatIi'        => 'M2',
      'tagIi'          => 'T2',
      'jhIi'           => 'Jh2',
      'ergIi'          => 'Erg2',
      'unsicher'       => 'unsicher:',
      'datierung'      => 'Datierung',
      'datierungIi'    => 'Datierung2',
      'texIdLang'      => 'TexIDlang', // TmNr + texLett + mehrfachKennung
    ); 
    
    function __construct(){
      parent::__construct('mentionedDate.xml');
    }

    function load(ObjectManager $manager)
    {
      foreach($this->xpath->evaluate('/fm:FMPXMLRESULT/fm:RESULTSET[1]/fm:ROW') as $row){
        $cols = $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row);
        $mentionedDate = new MentionedDate();
        
        $mentionedDate->setZeile($cols->item($this->positions['zeile'])->nodeValue);
        $mentionedDate->setJahr(self::makeInteger($cols->item($this->positions['jahr'])->nodeValue));
        $mentionedDate->setMonat(self::makeInteger($cols->item($this->positions['monat'])->nodeValue));
        $mentionedDate->setTag(self::makeInteger($cols->item($this->positions['tag'])->nodeValue));
        $mentionedDate->setJh(self::makeInteger($cols->item($this->positions['jh'])->nodeValue));
        $mentionedDate->setErg($cols->item($this->positions['erg'])->nodeValue);
        $mentionedDate->setJahrIi(self::makeInteger($cols->item($this->positions['jahrIi'])->nodeValue));
        $mentionedDate->setMonatIi(self::makeInteger($cols->item($this->positions['monatIi'])->nodeValue));
        $mentionedDate->setTagIi(self::makeInteger($cols->item($this->positions['tagIi'])->nodeValue));
        $mentionedDate->setJhIi(self::makeInteger($cols->item($this->positions['jhIi'])->nodeValue));
        $mentionedDate->setErgIi($cols->item($this->positions['ergIi'])->nodeValue);
        $mentionedDate->setUnsicher($cols->item($this->positions['unsicher'])->nodeValue);
        $mentionedDate->setDatierung($cols->item($this->positions['datierung'])->nodeValue);
        $mentionedDate->setDatierungIi($cols->item($this->positions['datierungIi'])->nodeValue);

        try{
          $hgv = $manager->getRepository('PapyrillioHgvBundle:Hgv')->findOneBy(array('id' => $cols->item($this->positions['texIdLang'])->nodeValue));

          if($hgv){
            $hgv->addMentionedDate($mentionedDate);
            $manager->persist($mentionedDate);
            echo $this->flushCounter . ': ' . $mentionedDate->getMetadata()->getId() . "\n";
            if(($this->flushCounter++ % 400) === 0){
              $manager->flush();
              $manager->clear();
            }
          } else {
            echo $this->flushCounter . ': ' . $cols->item($this->positions['texIdLang'])->nodeValue . " PROBLEM NO HGV METADATA RECORD FOUND\n";
          }
        } catch (Exception $e){
          echo $this->flushCounter . ': ' . $cols->item($this->positions['texIdLang'])->nodeValue . " EXCEPTION NO HGV METADATA RECORD FOUND\n";
        }
      }
      $manager->flush();
      $manager->clear();
    }

    public function getOrder(){
      return 2;
    }
}