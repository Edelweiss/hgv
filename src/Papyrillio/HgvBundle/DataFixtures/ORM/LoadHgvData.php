<?php
namespace Papyrillio\HgvBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Papyrillio\HgvBundle\Entity\Hgv;
use DateTime;
use DOMDocument;
use DOMXPath;

ini_set('memory_limit', -1);

class LoadHgvData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * 'hgvId' => 'hgvId', // tmNr + texLett
     * php app/console doctrine:fixtures:load --purge-with-truncate
     * php app/console doctrine:fixtures:load --append
     * 
     * **/

    const NAMESPACE_FILEMAKER = 'http://www.filemaker.com/fmpxmlresult';
    const NAMESPACE_TEI       = 'http://www.tei-c.org/ns/1.0';

    protected static $POSITIONS = array();

    protected static $FIELDS = array(
      'tmNr' => 'TM_Nr.',
      'texLett' => 'texLett',
      'mehrfachKennung' => 'MehrfachKennung',
      'texIdLang' => 'TexIDLang', // TmNr + texLett + mehrfachKennung
      'publikation' => 'Publikation',
      'band' => 'Band',
      'zusBand' => 'Zus.Band',
      'nummer' => 'Nummer',
      'seite' => 'Seite',
      'zusaetzlich' => 'zusätzlich',
      'publikationLang' => 'PublikationL',
      'material' => 'Material',
      'zusaetzlichSort' => 'ZusätzlichSort',
      'invNr' => 'InvNr',
      'jahr' => 'J',
      'monat' => 'M',
      'tag' => 'T',
      'jh' => 'Jh',
      'erg' => 'Erg',
      'jahrIi' => 'J2',
      'monatIi' => 'M2',
      'tagIi' => 'T2',
      'jhIi' => 'Jh2',
      'ergIi' => 'Erg2',
      'chronMinimum' => 'ChronMinimum',
      'chronMaximum' => 'ChronMaximum',
      'chronGlobal' => 'ChronGlobal',
      'erwaehnteDaten' => 'Erwähnte Daten',
      'unsicher' => 'unsicher:',
      'datierung' => 'Datierung',
      'datierungIi' => 'Datierung2',
      'anderePublikation' => 'Andere Publikation',
      'bl' => 'BL',
      'uebersetzungen' => 'Uebersetzungen',
      'abbildung' => 'Abbildung',
      'linkFm' => 'Link1FM',
      'ort' => 'Ort',
      'originaltitel' => 'Originaltitel',
      'originaltitelHtml' => 'OriginaltitelHTML',
      'inhalt' => 'Inhalt',
      'inhaltHtml' => 'InhaltHTML',
      'bemerkungen' => 'Bemerkungen',
      'daht' => 'DAHT',
      'ldab' => 'LDAB',
      'dfg' => 'DFG',
      'ddbSer' => 'ddbSer',
      'ddbVol' => 'ddbVol',
      'ddbDoc' => 'ddbDoc',
      'eingegebenAm' => 'eingegeben am',
      'zulGeaendertAm' => 'zul. geändert am',
      'DatensatzNr' => 'DatensatzNr.'
    ); 
    
    function __construct(){
      // xpath
      $doc = new DOMDocument();
      $doc->load(__DIR__ . '/../../Data/ohneVT_hgv.xml');
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
    }

    function load(ObjectManager $manager)
    {
      //var_dump(self::$POSITIONS);

      $flushCounter = 0;

      foreach($this->xpath->evaluate('/fm:FMPXMLRESULT/fm:RESULTSET[1]/fm:ROW') as $row){
        $cols = $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row);
        $hgv = new Hgv();
        $hgv->settmNr($cols->item(self::$POSITIONS['tmNr'])->nodeValue);
        $hgv->settexLett($cols->item(self::$POSITIONS['texLett'])->nodeValue);
        $hgv->setmehrfachKennung($cols->item(self::$POSITIONS['mehrfachKennung'])->nodeValue);
        $hgv->settexIdLang($cols->item(self::$POSITIONS['texIdLang'])->nodeValue);
        $hgv->setpublikation($cols->item(self::$POSITIONS['publikation'])->nodeValue);
        $hgv->setband($cols->item(self::$POSITIONS['band'])->nodeValue);
        $hgv->setzusBand($cols->item(self::$POSITIONS['zusBand'])->nodeValue);
        $hgv->setnummer($cols->item(self::$POSITIONS['nummer'])->nodeValue);
        $hgv->setseite($cols->item(self::$POSITIONS['seite'])->nodeValue);
        $hgv->setzusaetzlich($cols->item(self::$POSITIONS['zusaetzlich'])->nodeValue);
        $hgv->setpublikationLang($cols->item(self::$POSITIONS['publikationLang'])->nodeValue);
        $hgv->setmaterial($cols->item(self::$POSITIONS['material'])->nodeValue);
        $hgv->setzusaetzlichSort($cols->item(self::$POSITIONS['zusaetzlichSort'])->nodeValue);
        $hgv->setinvNr($cols->item(self::$POSITIONS['invNr'])->nodeValue);
        $hgv->setjahr($cols->item(self::$POSITIONS['jahr'])->nodeValue);
        $hgv->setmonat($cols->item(self::$POSITIONS['monat'])->nodeValue);
        $hgv->settag($cols->item(self::$POSITIONS['tag'])->nodeValue);
        $hgv->setjh($cols->item(self::$POSITIONS['jh'])->nodeValue);
        $hgv->seterg($cols->item(self::$POSITIONS['erg'])->nodeValue);
        $hgv->setjahrIi($cols->item(self::$POSITIONS['jahrIi'])->nodeValue);
        $hgv->setmonatIi($cols->item(self::$POSITIONS['monatIi'])->nodeValue);
        $hgv->settagIi($cols->item(self::$POSITIONS['tagIi'])->nodeValue);
        $hgv->setjhIi($cols->item(self::$POSITIONS['jhIi'])->nodeValue);
        $hgv->setergIi($cols->item(self::$POSITIONS['ergIi'])->nodeValue);
        $hgv->setchronMinimum($cols->item(self::$POSITIONS['chronMinimum'])->nodeValue);
        $hgv->setchronMaximum($cols->item(self::$POSITIONS['chronMaximum'])->nodeValue);
        $hgv->setchronGlobal($cols->item(self::$POSITIONS['chronGlobal'])->nodeValue);
        $hgv->seterwaehnteDaten($cols->item(self::$POSITIONS['erwaehnteDaten'])->nodeValue);
        $hgv->setunsicher($cols->item(self::$POSITIONS['unsicher'])->nodeValue);
        $hgv->setdatierung($cols->item(self::$POSITIONS['datierung'])->nodeValue);
        $hgv->setdatierungIi($cols->item(self::$POSITIONS['datierungIi'])->nodeValue);
        $hgv->setanderePublikation($cols->item(self::$POSITIONS['anderePublikation'])->nodeValue);
        $hgv->setbl($cols->item(self::$POSITIONS['bl'])->nodeValue);
        $hgv->setuebersetzungen($cols->item(self::$POSITIONS['uebersetzungen'])->nodeValue);
        $hgv->setabbildung($cols->item(self::$POSITIONS['abbildung'])->nodeValue);
        $hgv->setlinkFm($cols->item(self::$POSITIONS['linkFm'])->nodeValue);
        $hgv->setort($cols->item(self::$POSITIONS['ort'])->nodeValue);
        $hgv->setoriginaltitel($cols->item(self::$POSITIONS['originaltitel'])->nodeValue);
        $hgv->setoriginaltitelHtml($cols->item(self::$POSITIONS['originaltitelHtml'])->nodeValue);
        $hgv->setinhalt($cols->item(self::$POSITIONS['inhalt'])->nodeValue);
        $hgv->setinhaltHtml($cols->item(self::$POSITIONS['inhaltHtml'])->nodeValue);
        $hgv->setbemerkungen($cols->item(self::$POSITIONS['bemerkungen'])->nodeValue);
        $hgv->setdaht($cols->item(self::$POSITIONS['daht'])->nodeValue);
        $hgv->setldab($cols->item(self::$POSITIONS['ldab'])->nodeValue);
        $hgv->setdfg($cols->item(self::$POSITIONS['dfg'])->nodeValue);
        $hgv->setddbSer($cols->item(self::$POSITIONS['ddbSer'])->nodeValue);
        $hgv->setddbVol($cols->item(self::$POSITIONS['ddbVol'])->nodeValue);
        $hgv->setddbDoc($cols->item(self::$POSITIONS['ddbDoc'])->nodeValue);
        $hgv->setDatensatzNr($cols->item(self::$POSITIONS['DatensatzNr'])->nodeValue);

        $hgv->seteingegebenAm(self::makeDate($cols->item(self::$POSITIONS['eingegebenAm'])->nodeValue));
        $hgv->setzulGeaendertAm(self::makeDate($cols->item(self::$POSITIONS['zulGeaendertAm'])->nodeValue));

        $hgv->setHgvId($hgv->getTmNr() . $hgv->getTexLett());

        $manager->persist($hgv);

        echo $flushCounter . ': ' . $hgv->getPublikationLang() . ' (#' . $hgv->getTexIdLang() . ")\n";

        if(($flushCounter++ % 400) === 0){
          $manager->flush();
          $manager->clear();
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
      return 1;
    }
}
?>