<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;

class HgvController extends AbstractController{
  protected $request;
  protected $allParameters = [];

  public function __construct(RequestStack $requestStack)
  {
      $this->request = $requestStack->getCurrentRequest();
      $this->allParameters = array_merge($this->request->request->all(), $this->request->query->all());
  }
  protected function getParameter($key){
    if(array_key_exists($key, $this->allParameters)){
      return $this->allParameters[$key];
    }
  }

  protected function getSessionParameter($key){
    return $this->request->getSession()->get($key);
  }

  protected function setSessionParameter($key, $value){
    return $this->request->getSession()->set($key, $value);
  }

}
