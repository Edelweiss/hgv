<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class HgvController extends Controller{
  protected function getParameter($key){
    $get  = $this->getRequest()->query->get($key);
    $post = $this->getRequest()->request->get($key);
    
    if($post && (is_array($post) || strlen(trim($post)))){
      return $post;
    }

    return $get;
  }

  protected function getSessionParameter($key){
    return $this->getRequest()->getSession()->get($key);
  }

  protected function setSessionParameter($key, $value){
    return $this->getRequest()->getSession()->set($key, $value);
  }

}
