<?php

namespace Papyrillio\HgvBundle\Entity;

class Hgv
{
    /**
     * @var text $publikation
     */
    private $publikation;

    /**
     * @var integer $band
     */
    private $band;

    /**
     * @var text $zusBand
     */
    private $zusBand;

    /**
     * @var integer $nummer
     */
    private $nummer;

    /**
     * @var text $seite
     */
    private $seite;

    /**
     * @var text $zusaetzlich
     */
    private $zusaetzlich;

    /**
     * @var text $material
     */
    private $material;

    /**
     * @var integer $tmNr
     */
    private $tmNr;

    /**
     * @var text $hgvId
     */
    private $hgvId;

    /**
     * @var text $texLett
     */
    private $texLett;

    /**
     * @var text $mehrfachKennung
     */
    private $mehrfachKennung;

    /**
     * @var text $zusaetzlichSort
     */
    private $zusaetzlichSort;

    /**
     * @var text $invNr
     */
    private $invNr;

    /**
     * @var integer $jahr
     */
    private $jahr;

    /**
     * @var integer $monat
     */
    private $monat;

    /**
     * @var integer $tag
     */
    private $tag;

    /**
     * @var integer $jh
     */
    private $jh;

    /**
     * @var text $erg
     */
    private $erg;

    /**
     * @var integer $jahrIi
     */
    private $jahrIi;

    /**
     * @var integer $monatIi
     */
    private $monatIi;

    /**
     * @var integer $tagIi
     */
    private $tagIi;

    /**
     * @var integer $jhIi
     */
    private $jhIi;

    /**
     * @var text $ergIi
     */
    private $ergIi;

    /**
     * @var integer $chronMinimum
     */
    private $chronMinimum;

    /**
     * @var integer $chronMaximum
     */
    private $chronMaximum;

    /**
     * @var integer $chronGlobal
     */
    private $chronGlobal;

    /**
     * @var text $erwaehnteDaten
     */
    private $erwaehnteDaten;

    /**
     * @var text $unsicher
     */
    private $unsicher;

    /**
     * @var text $datierung
     */
    private $datierung;

    /**
     * @var text $datierungIi
     */
    private $datierungIi;

    /**
     * @var text $anderePublikation
     */
    private $anderePublikation;

    /**
     * @var text $bl
     */
    private $bl;

    /**
     * @var text $uebersetzungen
     */
    private $uebersetzungen;

    /**
     * @var text $abbildung
     */
    private $abbildung;

    /**
     * @var text $linkFm
     */
    private $linkFm;

    /**
     * @var text $ort
     */
    private $ort;

    /**
     * @var text $originaltitel
     */
    private $originaltitel;

    /**
     * @var text $originaltitelHtml
     */
    private $originaltitelHtml;

    /**
     * @var text $inhalt
     */
    private $inhalt;

    /**
     * @var text $inhaltHtml
     */
    private $inhaltHtml;

    /**
     * @var text $bemerkungen
     */
    private $bemerkungen;

    /**
     * @var text $daht
     */
    private $daht;

    /**
     * @var text $ldab
     */
    private $ldab;

    /**
     * @var text $dfg
     */
    private $dfg;

    /**
     * @var text $ddbSer
     */
    private $ddbSer;

    /**
     * @var text $ddbVol
     */
    private $ddbVol;

    /**
     * @var text $ddbDoc
     */
    private $ddbDoc;

    /**
     * @var datetime $eingegebenAm
     */
    private $eingegebenAm;

    /**
     * @var datetime $zulGeaendertAm
     */
    private $zulGeaendertAm;

    /**
     * @var integer $DatensatzNr
     */
    private $DatensatzNr;

    /**
     * Set publikation
     *
     * @param text $publikation
     */
    public function setPublikation($publikation)
    {
        $this->publikation = $publikation;
    }

    /**
     * Get publikation
     *
     * @return text 
     */
    public function getPublikation()
    {
        return $this->publikation;
    }

    /**
     * Set band
     *
     * @param integer $band
     */
    public function setBand($band)
    {
        $this->band = $band;
    }

    /**
     * Get band
     *
     * @return integer 
     */
    public function getBand()
    {
        return $this->band;
    }

    /**
     * Set zusBand
     *
     * @param text $zusBand
     */
    public function setZusBand($zusBand)
    {
        $this->zusBand = $zusBand;
    }

    /**
     * Get zusBand
     *
     * @return text 
     */
    public function getZusBand()
    {
        return $this->zusBand;
    }

    /**
     * Set nummer
     *
     * @param integer $nummer
     */
    public function setNummer($nummer)
    {
        $this->nummer = $nummer;
    }

    /**
     * Get nummer
     *
     * @return integer 
     */
    public function getNummer()
    {
        return $this->nummer;
    }

    /**
     * Set seite
     *
     * @param text $seite
     */
    public function setSeite($seite)
    {
        $this->seite = $seite;
    }

    /**
     * Get seite
     *
     * @return text 
     */
    public function getSeite()
    {
        return $this->seite;
    }

    /**
     * Set zusaetzlich
     *
     * @param text $zusaetzlich
     */
    public function setZusaetzlich($zusaetzlich)
    {
        $this->zusaetzlich = $zusaetzlich;
    }

    /**
     * Get zusaetzlich
     *
     * @return text 
     */
    public function getZusaetzlich()
    {
        return $this->zusaetzlich;
    }

    /**
     * Set material
     *
     * @param text $material
     */
    public function setMaterial($material)
    {
        $this->material = $material;
    }

    /**
     * Get material
     *
     * @return text 
     */
    public function getMaterial()
    {
        return $this->material;
    }

    /**
     * Set tmNr
     *
     * @param integer $tmNr
     */
    public function setTmNr($tmNr)
    {
        $this->tmNr = $tmNr;
    }

    /**
     * Get tmNr
     *
     * @return integer 
     */
    public function getTmNr()
    {
        return $this->tmNr;
    }

    /**
     * Set texLett
     *
     * @param text $texLett
     */
    public function setTexLett($texLett)
    {
        $this->texLett = $texLett;
    }

    /**
     * Get texLett
     *
     * @return text 
     */
    public function getTexLett()
    {
        return $this->texLett;
    }

    /**
     * Set mehrfachKennung
     *
     * @param text $mehrfachKennung
     */
    public function setMehrfachKennung($mehrfachKennung)
    {
        $this->mehrfachKennung = $mehrfachKennung;
    }

    /**
     * Get mehrfachKennung
     *
     * @return text 
     */
    public function getMehrfachKennung()
    {
        return $this->mehrfachKennung;
    }

    /**
     * Set zusaetzlichSort
     *
     * @param text $zusaetzlichSort
     */
    public function setZusaetzlichSort($zusaetzlichSort)
    {
        $this->zusaetzlichSort = $zusaetzlichSort;
    }

    /**
     * Get zusaetzlichSort
     *
     * @return text 
     */
    public function getZusaetzlichSort()
    {
        return $this->zusaetzlichSort;
    }

    /**
     * Set invNr
     *
     * @param text $invNr
     */
    public function setInvNr($invNr)
    {
        $this->invNr = $invNr;
    }

    /**
     * Get invNr
     *
     * @return text 
     */
    public function getInvNr()
    {
        return $this->invNr;
    }

    /**
     * Set jahr
     *
     * @param integer $jahr
     */
    public function setJahr($jahr)
    {
        $this->jahr = $jahr;
    }

    /**
     * Get jahr
     *
     * @return integer 
     */
    public function getJahr()
    {
        return $this->jahr;
    }

    /**
     * Set monat
     *
     * @param integer $monat
     */
    public function setMonat($monat)
    {
        $this->monat = $monat;
    }

    /**
     * Get monat
     *
     * @return integer 
     */
    public function getMonat()
    {
        return $this->monat;
    }

    /**
     * Set tag
     *
     * @param integer $tag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
    }

    /**
     * Get tag
     *
     * @return integer 
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Set jh
     *
     * @param integer $jh
     */
    public function setJh($jh)
    {
        $this->jh = $jh;
    }

    /**
     * Get jh
     *
     * @return integer 
     */
    public function getJh()
    {
        return $this->jh;
    }

    /**
     * Set erg
     *
     * @param text $erg
     */
    public function setErg($erg)
    {
        $this->erg = $erg;
    }

    /**
     * Get erg
     *
     * @return text 
     */
    public function getErg()
    {
        return $this->erg;
    }

    /**
     * Set jahrIi
     *
     * @param integer $jahrIi
     */
    public function setJahrIi($jahrIi)
    {
        $this->jahrIi = $jahrIi;
    }

    /**
     * Get jahrIi
     *
     * @return integer 
     */
    public function getJahrIi()
    {
        return $this->jahrIi;
    }

    /**
     * Set monatIi
     *
     * @param integer $monatIi
     */
    public function setMonatIi($monatIi)
    {
        $this->monatIi = $monatIi;
    }

    /**
     * Get monatIi
     *
     * @return integer 
     */
    public function getMonatIi()
    {
        return $this->monatIi;
    }

    /**
     * Set tagIi
     *
     * @param integer $tagIi
     */
    public function setTagIi($tagIi)
    {
        $this->tagIi = $tagIi;
    }

    /**
     * Get tagIi
     *
     * @return integer 
     */
    public function getTagIi()
    {
        return $this->tagIi;
    }

    /**
     * Set jhIi
     *
     * @param integer $jhIi
     */
    public function setJhIi($jhIi)
    {
        $this->jhIi = $jhIi;
    }

    /**
     * Get jhIi
     *
     * @return integer 
     */
    public function getJhIi()
    {
        return $this->jhIi;
    }

    /**
     * Set ergIi
     *
     * @param text $ergIi
     */
    public function setErgIi($ergIi)
    {
        $this->ergIi = $ergIi;
    }

    /**
     * Get ergIi
     *
     * @return text 
     */
    public function getErgIi()
    {
        return $this->ergIi;
    }

    /**
     * Set chronMinimum
     *
     * @param integer $chronMinimum
     */
    public function setChronMinimum($chronMinimum)
    {
        $this->chronMinimum = $chronMinimum;
    }

    /**
     * Get chronMinimum
     *
     * @return integer 
     */
    public function getChronMinimum()
    {
        return $this->chronMinimum;
    }

    /**
     * Set chronMaximum
     *
     * @param integer $chronMaximum
     */
    public function setChronMaximum($chronMaximum)
    {
        $this->chronMaximum = $chronMaximum;
    }

    /**
     * Get chronMaximum
     *
     * @return integer 
     */
    public function getChronMaximum()
    {
        return $this->chronMaximum;
    }

    /**
     * Set chronGlobal
     *
     * @param integer $chronGlobal
     */
    public function setChronGlobal($chronGlobal)
    {
        $this->chronGlobal = $chronGlobal;
    }

    /**
     * Get chronGlobal
     *
     * @return integer 
     */
    public function getChronGlobal()
    {
        return $this->chronGlobal;
    }

    /**
     * Set erwaehnteDaten
     *
     * @param text $erwaehnteDaten
     */
    public function setErwaehnteDaten($erwaehnteDaten)
    {
        $this->erwaehnteDaten = $erwaehnteDaten;
    }

    /**
     * Get erwaehnteDaten
     *
     * @return text 
     */
    public function getErwaehnteDaten()
    {
        return $this->erwaehnteDaten;
    }

    /**
     * Set unsicher
     *
     * @param text $unsicher
     */
    public function setUnsicher($unsicher)
    {
        $this->unsicher = $unsicher;
    }

    /**
     * Get unsicher
     *
     * @return text 
     */
    public function getUnsicher()
    {
        return $this->unsicher;
    }

    /**
     * Set datierung
     *
     * @param text $datierung
     */
    public function setDatierung($datierung)
    {
        $this->datierung = $datierung;
    }

    /**
     * Get datierung
     *
     * @return text 
     */
    public function getDatierung()
    {
        return $this->datierung;
    }

    /**
     * Set datierungIi
     *
     * @param text $datierungIi
     */
    public function setDatierungIi($datierungIi)
    {
        $this->datierungIi = $datierungIi;
    }

    /**
     * Get datierungIi
     *
     * @return text 
     */
    public function getDatierungIi()
    {
        return $this->datierungIi;
    }

    /**
     * Set anderePublikation
     *
     * @param text $anderePublikation
     */
    public function setAnderePublikation($anderePublikation)
    {
        $this->anderePublikation = $anderePublikation;
    }

    /**
     * Get anderePublikation
     *
     * @return text 
     */
    public function getAnderePublikation()
    {
        return $this->anderePublikation;
    }

    /**
     * Set bl
     *
     * @param text $bl
     */
    public function setBl($bl)
    {
        $this->bl = $bl;
    }

    /**
     * Get bl
     *
     * @return text 
     */
    public function getBl()
    {
        return $this->bl;
    }

    /**
     * Set uebersetzungen
     *
     * @param text $uebersetzungen
     */
    public function setUebersetzungen($uebersetzungen)
    {
        $this->uebersetzungen = $uebersetzungen;
    }

    /**
     * Get uebersetzungen
     *
     * @return text 
     */
    public function getUebersetzungen()
    {
        return $this->uebersetzungen;
    }

    /**
     * Set abbildung
     *
     * @param text $abbildung
     */
    public function setAbbildung($abbildung)
    {
        $this->abbildung = $abbildung;
    }

    /**
     * Get abbildung
     *
     * @return text 
     */
    public function getAbbildung()
    {
        return $this->abbildung;
    }

    /**
     * Set linkFm
     *
     * @param text $linkFm
     */
    public function setLinkFm($linkFm)
    {
        $this->linkFm = $linkFm;
    }

    /**
     * Get linkFm
     *
     * @return text 
     */
    public function getLinkFm()
    {
        return $this->linkFm;
    }

    /**
     * Set ort
     *
     * @param text $ort
     */
    public function setOrt($ort)
    {
        $this->ort = $ort;
    }

    /**
     * Get ort
     *
     * @return text 
     */
    public function getOrt()
    {
        return $this->ort;
    }

    /**
     * Set originaltitel
     *
     * @param text $originaltitel
     */
    public function setOriginaltitel($originaltitel)
    {
        $this->originaltitel = $originaltitel;
    }

    /**
     * Get originaltitel
     *
     * @return text 
     */
    public function getOriginaltitel()
    {
        return $this->originaltitel;
    }

    /**
     * Set originaltitelHtml
     *
     * @param text $originaltitelHtml
     */
    public function setOriginaltitelHtml($originaltitelHtml)
    {
        $this->originaltitelHtml = $originaltitelHtml;
    }

    /**
     * Get originaltitelHtml
     *
     * @return text 
     */
    public function getOriginaltitelHtml()
    {
        return $this->originaltitelHtml;
    }

    /**
     * Set inhalt
     *
     * @param text $inhalt
     */
    public function setInhalt($inhalt)
    {
        $this->inhalt = $inhalt;
    }

    /**
     * Get inhalt
     *
     * @return text 
     */
    public function getInhalt()
    {
        return $this->inhalt;
    }

    /**
     * Set inhaltHtml
     *
     * @param text $inhaltHtml
     */
    public function setInhaltHtml($inhaltHtml)
    {
        $this->inhaltHtml = $inhaltHtml;
    }

    /**
     * Get inhaltHtml
     *
     * @return text 
     */
    public function getInhaltHtml()
    {
        return $this->inhaltHtml;
    }

    /**
     * Set bemerkungen
     *
     * @param text $bemerkungen
     */
    public function setBemerkungen($bemerkungen)
    {
        $this->bemerkungen = $bemerkungen;
    }

    /**
     * Get bemerkungen
     *
     * @return text 
     */
    public function getBemerkungen()
    {
        return $this->bemerkungen;
    }

    /**
     * Set daht
     *
     * @param text $daht
     */
    public function setDaht($daht)
    {
        $this->daht = $daht;
    }

    /**
     * Get daht
     *
     * @return text 
     */
    public function getDaht()
    {
        return $this->daht;
    }

    /**
     * Set ldab
     *
     * @param text $ldab
     */
    public function setLdab($ldab)
    {
        $this->ldab = $ldab;
    }

    /**
     * Get ldab
     *
     * @return text 
     */
    public function getLdab()
    {
        return $this->ldab;
    }

    /**
     * Set dfg
     *
     * @param text $dfg
     */
    public function setDfg($dfg)
    {
        $this->dfg = $dfg;
    }

    /**
     * Get dfg
     *
     * @return text 
     */
    public function getDfg()
    {
        return $this->dfg;
    }

    /**
     * Set ddbSer
     *
     * @param text $ddbSer
     */
    public function setDdbSer($ddbSer)
    {
        $this->ddbSer = $ddbSer;
    }

    /**
     * Get ddbSer
     *
     * @return text 
     */
    public function getDdbSer()
    {
        return $this->ddbSer;
    }

    /**
     * Set ddbVol
     *
     * @param text $ddbVol
     */
    public function setDdbVol($ddbVol)
    {
        $this->ddbVol = $ddbVol;
    }

    /**
     * Get ddbVol
     *
     * @return text 
     */
    public function getDdbVol()
    {
        return $this->ddbVol;
    }

    /**
     * Set ddbDoc
     *
     * @param text $ddbDoc
     */
    public function setDdbDoc($ddbDoc)
    {
        $this->ddbDoc = $ddbDoc;
    }

    /**
     * Get ddbDoc
     *
     * @return text 
     */
    public function getDdbDoc()
    {
        return $this->ddbDoc;
    }

    /**
     * Set eingegebenAm
     *
     * @param datetime $eingegebenAm
     */
    public function setEingegebenAm($eingegebenAm)
    {
        $this->eingegebenAm = $eingegebenAm;
    }

    /**
     * Get eingegebenAm
     *
     * @return datetime 
     */
    public function getEingegebenAm()
    {
        return $this->eingegebenAm;
    }

    /**
     * Set zulGeaendertAm
     *
     * @param datetime $zulGeaendertAm
     */
    public function setZulGeaendertAm($zulGeaendertAm)
    {
        $this->zulGeaendertAm = $zulGeaendertAm;
    }

    /**
     * Get zulGeaendertAm
     *
     * @return datetime 
     */
    public function getZulGeaendertAm()
    {
        return $this->zulGeaendertAm;
    }

    /**
     * Set DatensatzNr
     *
     * @param integer $datensatzNr
     */
    public function setDatensatzNr($datensatzNr)
    {
        $this->DatensatzNr = $datensatzNr;
    }

    /**
     * Get DatensatzNr
     *
     * @return integer 
     */
    public function getDatensatzNr()
    {
        return $this->DatensatzNr;
    }

    /**
     * Set hgvId
     *
     * @param text $hgvId
     */
    public function setHgvId($hgvId)
    {
        $this->hgvId = $hgvId;
    }

    /**
     * Get hgvId
     *
     * @return text 
     */
    public function getHgvId()
    {
        return $this->hgvId;
    }
    /**
     * @var text $publikationLang
     */
    private $publikationLang;


    /**
     * Set publikationLang
     *
     * @param text $publikationLang
     */
    public function setPublikationLang($publikationLang)
    {
        $this->publikationLang = $publikationLang;
    }

    /**
     * Get publikationLang
     *
     * @return text 
     */
    public function getPublikationLang()
    {
        return $this->publikationLang;
    }
    /**
     * @var Papyrillio\HgvBundle\Entity\MentionedDate
     */
    private $mentionedDates;

    public function __construct($id = null)
    {
        if($id !== null){
          $this->setId($id);
        }
        $this->mentionedDates = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add mentionedDates
     *
     * @param Papyrillio\HgvBundle\Entity\MentionedDate $mentionedDates
     */
    public function addMentionedDate(\Papyrillio\HgvBundle\Entity\MentionedDate $mentionedDates)
    {
        $mentionedDates->setMetadata($this);
        $this->mentionedDates[] = $mentionedDates;
    }

    /**
     * Get mentionedDates
     *
     * @return Doctrine\Common\Collections\Collection 
     */
    public function getMentionedDates()
    {
        return $this->mentionedDates;
    }
    /**
     * @var string $id
     */
    private $id;


    /**
     * Set id
     *
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return string 
     */
    public function getId()
    {
        return $this->id;
    }
}