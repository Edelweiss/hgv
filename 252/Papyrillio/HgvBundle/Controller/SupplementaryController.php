<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class SupplementaryController extends HgvController
{
  public function textAction($id)
  {
    $tm = preg_replace('/[^\d]+/', '', $id);
    $texLett = preg_replace('/\d+/', '', $id);
    
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');
    $data = $repository->findBy(array('tmNr' => $tm, 'texLett' => $texLett), array('mehrfachKennung' => 'ASC'));

    $record = null;
    if(count($data)){
      $record = $data[0];
    }

    return $this->render('PapyrillioHgvBundle:Supplementary:text.html.twig', array('record' => $record));
  }

  public function translationAction($id)
  {
    $tm = preg_replace('/[^\d]+/', '', $id);
    $texLett = preg_replace('/\d+/', '', $id);
    
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');
    $data = $repository->findBy(array('tmNr' => $tm, 'texLett' => $texLett), array('mehrfachKennung' => 'ASC'));

    $record = null;
    if(count($data)){
      $record = $data[0];
    }

    return $this->render('PapyrillioHgvBundle:Supplementary:translation.html.twig', array('record' => $record));
  }

  public function pictureAction($id)
  {
    $tm = preg_replace('/[^\d]+/', '', $id);
    $texLett = preg_replace('/\d+/', '', $id);
    
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');
    $data = $repository->findBy(array('tmNr' => $tm, 'texLett' => $texLett), array('mehrfachKennung' => 'ASC'));

    $record = null;
    if(count($data)){
      $record = $data[0];
    }

    return $this->render('PapyrillioHgvBundle:Supplementary:picture.html.twig', array('record' => $record));
  }
  
  public function dashboardConfigurationAction()
  {
    $configuration = array(
      'text'        => array('show' => true, 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100),
      'translation' => array('show' => true, 'x' => 528, 'y' => 0, 'width' => 1103, 'height' => 392),
      'picture'     => array('show' => true, 'x' => 0, 'y' => 474, 'width' => 628, 'height' => 502));

    if($this->getParameter('dashboardConfiguration')){
      $configuration = $this->getParameter('dashboardConfiguration');
      $configuration['text']['show'] = $configuration['text']['show'] === false || $configuration['text']['show'] === 'false' ? false : true; 
      $configuration['translation']['show'] = $configuration['translation']['show'] === false || $configuration['translation']['show'] === 'false' ? false : true;
      $configuration['picture']['show'] = $configuration['picture']['show'] === false || $configuration['picture']['show'] === 'false' ? false : true;
      $this->setSessionParameter('dashboardConfiguration', $configuration);
    }

    if($this->getSessionParameter('dashboardConfiguration')){ 
      $configuration = $this->getSessionParameter('dashboardConfiguration');
    }

    return new Response(json_encode($configuration));
  }

}
