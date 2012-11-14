<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ShortcutController extends HgvController
{
  public function hgvAction($id)
  {
    $tm = preg_replace('/[^\d]+/', '', $id);
    $texLett = preg_replace('/\d+/', '', $id);
    if(empty($texLett)){
      $texLett = '=';
    }

    return $this->forward('PapyrillioHgvBundle:Catalogue:show', array(), array(
      'search'  => array('criteria' => array(
        'TM_Nr.' => array('value' => $tm, 'operator' => 'eq'), 
        'texLett' => array('value' => $texLett, 'operator' => 'eq')))
    ));
  }

  public function tmAction($id)
  {
    return $this->forward('PapyrillioHgvBundle:Catalogue:show', array(), array(
      'search'  => array('criteria' => array('TM_Nr.' => array('value' => $id, 'operator' => 'eq')))
    ));
  }

  public function ddbAction($id)
  {
    $ddb = explode(';', $id); // routing requirement makes sure that there are two »;«

    return $this->forward('PapyrillioHgvBundle:Catalogue:show', array(), array(
      'search'  => array('criteria' => array(
        'ddbSer' => array('value' => $ddb[0], 'operator' => 'eq'), 
        'ddbVol' => array('value' => $ddb[1], 'operator' => 'eq'), 
        'ddbDoc' => array('value' => $ddb[2], 'operator' => 'eq')))
    ));

  }
}
