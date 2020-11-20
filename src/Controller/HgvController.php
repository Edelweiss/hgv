<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;

class HgvController extends AbstractController{
  protected $request;

  public function __construct(RequestStack $requestStack)
  {
      $this->request = $requestStack->getCurrentRequest();
  }
  protected function getParameter($key){
    $get  = $this->request->query->get($key);
    $post = $this->request->request->get($key);

    if($post && (is_array($post) || strlen(trim($post)))){
      return $post;
    }

    return $get;
  }

  protected function getSessionParameter($key){
    return $this->request->getSession()->get($key);
  }

  protected function setSessionParameter($key, $value){
    return $this->request->getSession()->set($key, $value);
  }

}
