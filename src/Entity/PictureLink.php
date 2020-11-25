<?php

namespace App\Entity;

use App\Repository\PictureLinkRepository;

class PictureLink
{

    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var text $institution
     */
    private $institution;

    /**
     * @var text $name
     */
    private $name;

    /**
     * @var text $url
     */
    private $url;

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
     * Set institution
     *
     * @param text $institution
     */
    public function setInstitution($institution)
    {
        $this->institution = $institution;
    }

    /**
     * Get institution
     *
     * @return text 
     */
    public function getInstitution()
    {
        return $this->institution;
    }

    /**
     * Set name
     *
     * @param text $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get name
     *
     * @return text 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set url
     *
     * @param text $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return text 
     */
    public function getUrl()
    {
        return $this->url;
    }
    
    public function __construct($linkFm){
        $this->setUrl(trim($linkFm));
        if(preg_match('|^https?://([^?/]+)[?/]|', $linkFm, $matches)){
            $this->setInstitution($matches[1]);
        }
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

    public function __toString(){
        return $this->institution . ' (' . $this->url . ')';
    }

}