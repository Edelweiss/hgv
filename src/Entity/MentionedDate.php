<?php

namespace App\Entity;

use App\Repository\MentionedDateRepository;

class MentionedDate
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var text $zeile
     */
    private $zeile;

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
     * @var Papyrillio\HgvBundle\Entity\Hgv
     */
    private $metadata;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set zeile
     *
     * @param text $zeile
     */
    public function setZeile($zeile)
    {
        $this->zeile = $zeile;
    }

    /**
     * Get zeile
     *
     * @return text 
     */
    public function getZeile()
    {
        return $this->zeile;
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
     * Set metadata
     *
     * @param Papyrillio\HgvBundle\Entity\Hgv $metadata
     */
    public function setMetadata(\Papyrillio\HgvBundle\Entity\Hgv $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return Papyrillio\HgvBundle\Entity\Hgv 
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

}