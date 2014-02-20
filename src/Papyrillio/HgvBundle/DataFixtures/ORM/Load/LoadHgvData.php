<?php
namespace Papyrillio\HgvBundle\DataFixtures\ORM\Load;

use Papyrillio\HgvBundle\DataFixtures\ORM\XmlData;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Papyrillio\HgvBundle\Entity\Hgv;
use DateTime;
use DOMDocument;
use DOMXPath;

/*
 * THIS SCRIPT IS NOT UP-TO-DATE WITH THE CURRENT DATABASE MODEL !!!
 * Perhaps this script is no longer needed because the update script can do a better job.
 * However, the update script does not deal with Mentioned Dates by itself.
 * Perhaps this needs a new implementation as well because Mentioned Dates now live within the HGV database and are linked by relation.
 * 
 * */

class LoadHgvData extends XmlData
{
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

    function _load(ObjectManager $manager)
    {
      
    }

    function load(ObjectManager $manager)
    {
      foreach($this->xpath->evaluate('/fm:FMPXMLRESULT/fm:RESULTSET[1]/fm:ROW') as $row){
        $cols = $this->xpath->evaluate('fm:COL/fm:DATA[1]', $row);
        $hgv = new Hgv($cols->item($this->positions['texIdLang'])->nodeValue);
        $hgv->settmNr(self::makeInteger($cols->item($this->positions['tmNr'])->nodeValue));
        $hgv->settexLett($cols->item($this->positions['texLett'])->nodeValue);
        $hgv->setmehrfachKennung($cols->item($this->positions['mehrfachKennung'])->nodeValue);
        $hgv->setpublikation($cols->item($this->positions['publikation'])->nodeValue);
        $hgv->setband(self::makeInteger($cols->item($this->positions['band'])->nodeValue));
        $hgv->setzusBand($cols->item($this->positions['zusBand'])->nodeValue);
        $hgv->setnummer(self::makeInteger($cols->item($this->positions['nummer'])->nodeValue));
        $hgv->setseite($cols->item($this->positions['seite'])->nodeValue);
        $hgv->setzusaetzlich($cols->item($this->positions['zusaetzlich'])->nodeValue);
        $hgv->setpublikationLang($cols->item($this->positions['publikationLang'])->nodeValue);
        $hgv->setmaterial($cols->item($this->positions['material'])->nodeValue);
        $hgv->setzusaetzlichSort($cols->item($this->positions['zusaetzlichSort'])->nodeValue);
        $hgv->setinvNr($cols->item($this->positions['invNr'])->nodeValue);
        $hgv->setjahr(self::makeInteger($cols->item($this->positions['jahr'])->nodeValue));
        $hgv->setmonat(self::makeInteger($cols->item($this->positions['monat'])->nodeValue));
        $hgv->settag(self::makeInteger($cols->item($this->positions['tag'])->nodeValue));
        $hgv->setjh(self::makeInteger($cols->item($this->positions['jh'])->nodeValue));
        $hgv->seterg($cols->item($this->positions['erg'])->nodeValue);
        $hgv->setjahrIi(self::makeInteger($cols->item($this->positions['jahrIi'])->nodeValue));
        $hgv->setmonatIi(self::makeInteger($cols->item($this->positions['monatIi'])->nodeValue));
        $hgv->settagIi(self::makeInteger($cols->item($this->positions['tagIi'])->nodeValue));
        $hgv->setjhIi(self::makeInteger($cols->item($this->positions['jhIi'])->nodeValue));
        $hgv->setergIi($cols->item($this->positions['ergIi'])->nodeValue);
        $hgv->setchronMinimum(self::makeInteger($cols->item($this->positions['chronMinimum'])->nodeValue));
        $hgv->setchronMaximum(self::makeInteger($cols->item($this->positions['chronMaximum'])->nodeValue));
        $hgv->setchronGlobal(self::makeInteger($cols->item($this->positions['chronGlobal'])->nodeValue));
        $hgv->seterwaehnteDaten($cols->item($this->positions['erwaehnteDaten'])->nodeValue);
        $hgv->setunsicher($cols->item($this->positions['unsicher'])->nodeValue);
        $hgv->setdatierung($cols->item($this->positions['datierung'])->nodeValue);
        $hgv->setdatierungIi($cols->item($this->positions['datierungIi'])->nodeValue);
        $hgv->setanderePublikation($cols->item($this->positions['anderePublikation'])->nodeValue);
        $hgv->setbl($cols->item($this->positions['bl'])->nodeValue);
        $hgv->setuebersetzungen($cols->item($this->positions['uebersetzungen'])->nodeValue);
        $hgv->setabbildung($cols->item($this->positions['abbildung'])->nodeValue);
        $hgv->setlinkFm($cols->item($this->positions['linkFm'])->nodeValue);
        $hgv->setort($cols->item($this->positions['ort'])->nodeValue);
        $hgv->setoriginaltitel($cols->item($this->positions['originaltitel'])->nodeValue);
        $hgv->setoriginaltitelHtml($cols->item($this->positions['originaltitelHtml'])->nodeValue);
        $hgv->setinhalt($cols->item($this->positions['inhalt'])->nodeValue);
        $hgv->setinhaltHtml($cols->item($this->positions['inhaltHtml'])->nodeValue);
        $hgv->setbemerkungen($cols->item($this->positions['bemerkungen'])->nodeValue);
        $hgv->setdaht($cols->item($this->positions['daht'])->nodeValue);
        $hgv->setldab($cols->item($this->positions['ldab'])->nodeValue);
        $hgv->setdfg($cols->item($this->positions['dfg'])->nodeValue);
        $hgv->setddbSer($cols->item($this->positions['ddbSer'])->nodeValue);
        $hgv->setddbVol($cols->item($this->positions['ddbVol'])->nodeValue);
        $hgv->setddbDoc($cols->item($this->positions['ddbDoc'])->nodeValue);
        $hgv->setDatensatzNr(self::makeInteger($cols->item($this->positions['DatensatzNr'])->nodeValue));

        $hgv->seteingegebenAm(self::makeDate($cols->item($this->positions['eingegebenAm'])->nodeValue));
        $hgv->setzulGeaendertAm(self::makeDate($cols->item($this->positions['zulGeaendertAm'])->nodeValue));

        $hgv->setHgvId($hgv->getTmNr() . $hgv->getTexLett());

        $manager->persist($hgv);

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
      return 1;
    }
}