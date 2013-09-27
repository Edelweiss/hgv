<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
  public function indexAction(){
    return $this->render('PapyrillioHgvBundle:Default:index.html.twig');
  }

  public function abbreviationAction(){
    return $this->render('PapyrillioHgvBundle:Default:abbreviation.html.twig');
  }

  public function helpAction($topic = '', $language = ''){
    return $this->render('PapyrillioHgvBundle:Default:help' . ucfirst($topic) . ucfirst($language) . '.html.twig');
  }

  public function introductionAction(){
    return $this->render('PapyrillioHgvBundle:Default:introduction.html.twig');
  }

  public function publicationAction(){
    return $this->render('PapyrillioHgvBundle:Default:publication.html.twig');
  }
}
