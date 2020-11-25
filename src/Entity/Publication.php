<?php

namespace App\Entity;

use App\Repository\PublicationRepository;

class Publication
{
    protected static $REPRINTS = array();

    public static function setReprints($reprints){ // $reprints array of arrays or type ['collection' => '', 'volume' => '', 'particle' => '']
      self::$REPRINTS = array();
      foreach($reprints as $reprint){
        self::$REPRINTS[] = $reprint['collection'] . $reprint['volume'] . $reprint['particle'];
      }
    }

    public function totalReprint(){
      $key = $this->collection . $this->volume . $this->particle;
      if(in_array($key, self::$REPRINTS)){
        return true;
      }
      return false;
    }

    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var text $collection
     */
    private $collection;

    /**
     * @var integer $volume
     */
    private $volume;

    /**
     * @var text $particle
     */
    private $particle;

    /**
     * @var integer $number
     */
    private $number;

    /**
     * @var text $side
     */
    private $side;

    /**
     * @var text $extra
     */
    private $extra;

    /**
     * @var Papyrillio\HgvBundle\Entity\Publication
     */
    private $children;

    /**
     * @var Papyrillio\HgvBundle\Entity\Publication
     */
    private $parent;

    public function __construct()
    {
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
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
     * Set collection
     *
     * @param text $collection
     */
    public function setCollection($collection)
    {
        $this->collection = $collection;
    }

    /**
     * Get collection
     *
     * @return text 
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Set volume
     *
     * @param integer $volume
     */
    public function setVolume($volume)
    {
        $this->volume = $volume;
    }

    /**
     * Get volume
     *
     * @return integer 
     */
    public function getVolume()
    {
        return $this->volume;
    }

    /**
     * Set particle
     *
     * @param text $particle
     */
    public function setParticle($particle)
    {
        $this->particle = $particle;
    }

    /**
     * Get particle
     *
     * @return text 
     */
    public function getParticle()
    {
        return $this->particle;
    }

    /**
     * Set number
     *
     * @param integer $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }

    /**
     * Get number
     *
     * @return integer 
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set side
     *
     * @param text $side
     */
    public function setSide($side)
    {
        $this->side = $side;
    }

    /**
     * Get side
     *
     * @return text 
     */
    public function getSide()
    {
        return $this->side;
    }

    /**
     * Set extra
     *
     * @param text $extra
     */
    public function setExtra($extra)
    {
        $this->extra = $extra;
    }

    /**
     * Get extra
     *
     * @return text 
     */
    public function getExtra()
    {
        return $this->extra;
    }

    /**
     * Add children
     *
     * @param Papyrillio\HgvBundle\Entity\Publication $children
     */
    public function addPublication(\Papyrillio\HgvBundle\Entity\Publication $children)
    {
        $this->children[] = $children;
    }

    /**
     * Get children
     *
     * @return Doctrine\Common\Collections\Collection 
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set parent
     *
     * @param Papyrillio\HgvBundle\Entity\Publication $parent
     */
    public function setParent(\Papyrillio\HgvBundle\Entity\Publication $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Get parent
     *
     * @return Papyrillio\HgvBundle\Entity\Publication 
     */
    public function getParent()
    {
        return $this->parent;
    }
}