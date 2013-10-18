<?php
namespace Papyrillio\HgvBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Papyrillio\HgvBundle\Entity\MentionedDate;
use DateTime;
use DOMDocument;
use DOMXPath;

ini_set('memory_limit', -1);

class LoadMentionedDateData extends AbstractFixture implements OrderedFixtureInterface
{      
    const NAMESPACE_FILEMAKER = 'http://www.filemaker.com/fmpxmlresult';
    const NAMESPACE_TEI       = 'http://www.tei-c.org/ns/1.0';

    protected static $POSITIONS = array();

    protected static $FIELDS = array(
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
      'erwaehnteDaten' => 'Erwähnte Daten',
      'texIdLang'      => 'TexIDlang', // TmNr + texLett + mehrfachKennung
    ); 
    
    function __construct(){
      // xpath
      $doc = new DOMDocument();
      $doc->load(__DIR__ . '/../../Data/mentionedDate.xml');
      $xpath = new DOMXPath($doc);
      $xpath->registerNamespace('fm', self::NAMESPACE_FILEMAKER);
      $this->xpath = $xpath;

      // column positions
      foreach(self::$FIELDS as $key => $filemakerName){
        $position = $this->xpath->evaluate("/fm:FMPXMLRESULT/fm:METADATA[1]/fm:FIELD[@NAME='" . $filemakerName . "']");
        if($position->length > 0){
          $position = preg_replace('/^.+\[(\d+)\]$/', '$1', $position->item(0)->getNodePath());
          self::$POSITIONS[$key] = $position - 1;
        }
      }
      var_dump(self::$POSITIONS);
    }

    function load(ObjectManager $manager)
    {
      $flushCounter = 0;

      foreach($this->xpath->evaluate('/fm:FMPXMLRESULT/fm:RESULTSET[1]/fm:ROW') as $row){
        $cols = $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row);
        $mentionedDate = new MentionedDate();
        
        $mentionedDate->setjahr($cols->item(self::$POSITIONS['jahr'])->nodeValue);
        $mentionedDate->setmonat($cols->item(self::$POSITIONS['monat'])->nodeValue);
        $mentionedDate->settag($cols->item(self::$POSITIONS['tag'])->nodeValue);
        $mentionedDate->setjh($cols->item(self::$POSITIONS['jh'])->nodeValue);
        $mentionedDate->seterg($cols->item(self::$POSITIONS['erg'])->nodeValue);
        $mentionedDate->setjahrIi($cols->item(self::$POSITIONS['jahrIi'])->nodeValue);
        $mentionedDate->setmonatIi($cols->item(self::$POSITIONS['monatIi'])->nodeValue);
        $mentionedDate->settagIi($cols->item(self::$POSITIONS['tagIi'])->nodeValue);
        $mentionedDate->setjhIi($cols->item(self::$POSITIONS['jhIi'])->nodeValue);
        $mentionedDate->setergIi($cols->item(self::$POSITIONS['ergIi'])->nodeValue);
        $mentionedDate->seterwaehnteDaten($cols->item(self::$POSITIONS['erwaehnteDaten'])->nodeValue);
        $mentionedDate->setunsicher($cols->item(self::$POSITIONS['unsicher'])->nodeValue);
        $mentionedDate->setdatierung($cols->item(self::$POSITIONS['datierung'])->nodeValue);
        $mentionedDate->setdatierungIi($cols->item(self::$POSITIONS['datierungIi'])->nodeValue);

        try{
          $hgv = $manager->getRepository('PapyrillioHgvBundle:Hgv')->findOneBy(array('id' => $cols->item(self::$POSITIONS['texIdLang'])->nodeValue));

          if($hgv){
            $hgv->addMentionedDate($mentionedDate);
            $manager->persist($mentionedDate);
            echo $flushCounter . ': ' . $mentionedDate->getMetadata()->getId() . "\n";
            if(($flushCounter++ % 400) === 0){
              $manager->flush();
              $manager->clear();
            }
          } else {
            echo $flushCounter . ': ' . $cols->item(self::$POSITIONS['texIdLang'])->nodeValue . " PROBLEM NO HGV METADATA RECORD FOUND\n";
          }
        } catch (Exception $e){
          echo $flushCounter . ': ' . $cols->item(self::$POSITIONS['texIdLang'])->nodeValue . " EXCEPTION NO HGV METADATA RECORD FOUND\n";
        }
      }
      $manager->flush();
      $manager->clear();
    }

    protected static function makeDate($fmDate){
      if(preg_match('/\d\d.\d\d.\d\d\d\d/', $fmDate)){
        return new DateTime(substr($fmDate, 6, 4) . '-' . substr($fmDate, 3, 2) . '-' . substr($fmDate, 0, 2));
      }
      return new DateTime();
    }

    public function getOrder(){
      return 2;
    }
}
?>