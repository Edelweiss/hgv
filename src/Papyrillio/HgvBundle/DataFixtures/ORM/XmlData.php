<?php
namespace Papyrillio\HgvBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Papyrillio\HgvBundle\Entity\Hgv;
use Papyrillio\HgvBundle\Entity\PictureLink;
use DateTime;
use DOMDocument;
use DOMXPath;

ini_set('memory_limit', -1);

abstract class XmlData extends AbstractFixture implements OrderedFixtureInterface
{
    /**
     * php app/console doctrine:fixtures:load --purge-with-truncate
     * php app/console doctrine:fixtures:load --append --fixtures=src/Papyrillio/HgvBundle/DataFixtures/ORM/Load
     * php app/console doctrine:fixtures:load --append --fixtures=src/Papyrillio/HgvBundle/DataFixtures/ORM/Update
     * 
     * **/

    const NAMESPACE_FILEMAKER = 'http://www.filemaker.com/fmpxmlresult';
    const NAMESPACE_TEI       = 'http://www.tei-c.org/ns/1.0';
    
    protected $flushCounter = 0;
    protected $xpath = null;

    protected $positions = array();

    protected $fields = array(
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
      'blOnline' => 'blOnline',
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
      'ddbSerIdp' => 'ddbSerIDP',
      'ddbVol' => 'ddbVol',
      'ddbDoc' => 'ddbDoc',
      'eingegebenAm' => 'eingegeben am',
      'zulGeaendertAm' => 'zul. geändert am',
      'DatensatzNr' => 'DatensatzNr.'
    ); 

    function __construct($importFile = 'hgv.xml'){
      // xpath
      $doc = new DOMDocument();
      $doc->load(__DIR__ . '/../../Data/' . $importFile);
      $xpath = new DOMXPath($doc);
      $xpath->registerNamespace('fm', self::NAMESPACE_FILEMAKER);
      $this->xpath = $xpath;
      
      // column positions
      foreach($this->fields as $key => $filemakerName){
        $position = $this->xpath->evaluate("/fm:FMPXMLRESULT/fm:METADATA[1]/fm:FIELD[@NAME='" . $filemakerName . "']");
        if($position->length > 0){
          $position = preg_replace('/^.+\[(\d+)\]$/', '$1', $position->item(0)->getNodePath());
          $this->positions[$key] = $position - 1;
        }
      }
    }

    function load(ObjectManager $manager){
      
    }

    protected static function makeInteger($fmInt){
      return str_replace(' ', '', $fmInt);
    }

    protected static function makeDate($fmDate){
      if(preg_match('/\d\d.\d\d.\d\d\d\d/', $fmDate)){
        return new DateTime(substr($fmDate, 6, 4) . '-' . substr($fmDate, 3, 2) . '-' . substr($fmDate, 0, 2));
      }
      return new DateTime();
    }
    
    protected function generateObjectFromXml($row, $hgv = null){
        $cols = $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row);

        if($hgv === null){
          $hgv = new Hgv($cols->item($this->positions['texIdLang'])->nodeValue);
          $hgv->setCreatedAt(new DateTime());
        }
        $hgv->setModifiedAt(new DateTime());

        $hgv->settmNr($cols->item($this->positions['tmNr'])->nodeValue);
        $hgv->settexLett($cols->item($this->positions['texLett'])->nodeValue);
        $hgv->setmehrfachKennung($cols->item($this->positions['mehrfachKennung'])->nodeValue);
        $hgv->setpublikation($cols->item($this->positions['publikation'])->nodeValue);
        $hgv->setband($cols->item($this->positions['band'])->nodeValue);
        $hgv->setzusBand($cols->item($this->positions['zusBand'])->nodeValue);
        $hgv->setnummer($cols->item($this->positions['nummer'])->nodeValue);
        $hgv->setseite($cols->item($this->positions['seite'])->nodeValue);
        $hgv->setzusaetzlich($cols->item($this->positions['zusaetzlich'])->nodeValue);
        $hgv->setpublikationLang($cols->item($this->positions['publikationLang'])->nodeValue);
        $hgv->setmaterial($cols->item($this->positions['material'])->nodeValue);
        $hgv->setzusaetzlichSort($cols->item($this->positions['zusaetzlichSort'])->nodeValue);
        $hgv->setinvNr($cols->item($this->positions['invNr'])->nodeValue);
        $hgv->setjahr($cols->item($this->positions['jahr'])->nodeValue);
        $hgv->setmonat($cols->item($this->positions['monat'])->nodeValue);
        $hgv->settag($cols->item($this->positions['tag'])->nodeValue);
        $hgv->setjh($cols->item($this->positions['jh'])->nodeValue);
        $hgv->seterg($cols->item($this->positions['erg'])->nodeValue);
        $hgv->setjahrIi($cols->item($this->positions['jahrIi'])->nodeValue);
        $hgv->setmonatIi($cols->item($this->positions['monatIi'])->nodeValue);
        $hgv->settagIi($cols->item($this->positions['tagIi'])->nodeValue);
        $hgv->setjhIi($cols->item($this->positions['jhIi'])->nodeValue);
        $hgv->setergIi($cols->item($this->positions['ergIi'])->nodeValue);
        $hgv->setchronMinimum($cols->item($this->positions['chronMinimum'])->nodeValue);
        $hgv->setchronMaximum($cols->item($this->positions['chronMaximum'])->nodeValue);
        $hgv->setchronGlobal($cols->item($this->positions['chronGlobal'])->nodeValue);
        $hgv->seterwaehnteDaten($cols->item($this->positions['erwaehnteDaten'])->nodeValue);
        $hgv->setunsicher($cols->item($this->positions['unsicher'])->nodeValue);
        $hgv->setdatierung($cols->item($this->positions['datierung'])->nodeValue);
        $hgv->setdatierungIi($cols->item($this->positions['datierungIi'])->nodeValue);
        $hgv->setanderePublikation($cols->item($this->positions['anderePublikation'])->nodeValue);
        $hgv->setbl($cols->item($this->positions['bl'])->nodeValue);
        $hgv->setblOnline($cols->item($this->positions['blOnline'])->nodeValue);
        $hgv->setuebersetzungen($cols->item($this->positions['uebersetzungen'])->nodeValue);
        $hgv->setabbildung($cols->item($this->positions['abbildung'])->nodeValue);
        $hgv->setort($cols->item($this->positions['ort'])->nodeValue);
        $hgv->setoriginaltitel($cols->item($this->positions['originaltitel'])->nodeValue);
        $hgv->setoriginaltitelHtml($cols->item($this->positions['originaltitelHtml'])->nodeValue);
        $hgv->setinhalt($cols->item($this->positions['inhalt'])->nodeValue);
        $hgv->setinhaltHtml($cols->item($this->positions['inhaltHtml'])->nodeValue);
        $hgv->setbemerkungen($cols->item($this->positions['bemerkungen'])->nodeValue);
        $hgv->setdaht($cols->item($this->positions['daht'])->nodeValue);
        $hgv->setldab($cols->item($this->positions['ldab'])->nodeValue);
        $hgv->setdfg($cols->item($this->positions['dfg'])->nodeValue);
        $ddbSer = $cols->item($this->positions['ddbSer'])->nodeValue;
        if(!$ddbSer or $ddbSer == ''){
          $ddbSer = $cols->item($this->positions['ddbSerIdp'])->nodeValue;
        }
        $hgv->setddbSer($ddbSer);
        $hgv->setddbVol($cols->item($this->positions['ddbVol'])->nodeValue);
        $hgv->setddbDoc($cols->item($this->positions['ddbDoc'])->nodeValue);
        $hgv->setDatensatzNr($cols->item($this->positions['DatensatzNr'])->nodeValue);

        $hgv->seteingegebenAm(self::makeDate($cols->item($this->positions['eingegebenAm'])->nodeValue));
        $hgv->setzulGeaendertAm(self::makeDate($cols->item($this->positions['zulGeaendertAm'])->nodeValue));

        $hgv->setHgvId($hgv->getTmNr() . $hgv->getTexLett());

        if($hgv->getPictureLinks()){
          $hgv->getPictureLinks()->clear();
        } // due to definition »orphanRemoval: true« in »Hgv.orm.yml« old picture links will be deleted (i.e. removed) automatically

        foreach($this->xpath->evaluate('fm:COL[' . ($this->positions['linkFm'] + 1) . ']/fm:DATA', $row) as $linkFm){
          if($linkFm->nodeValue){
            $pictureLink = new PictureLink($linkFm->nodeValue);
            $hgv->addPictureLink($pictureLink);
          }
        } // due to definition »cascade: ['persist', 'remove']« in »Hgv.orm.yml« these picture links will become persistent as soon as the owning HGV record will become persistent
        return $hgv;
    }
}
?>