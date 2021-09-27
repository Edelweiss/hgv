<?php

namespace App\Controller;

use App\Entity\Hgv;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ShortcutController extends HgvController
{
  public function hgv($id): Response
  {
    $tm = preg_replace('/[^\d]+/', '', $id);
    $texLett = preg_replace('/\d+/', '', $id);
    
    $entityManager = $this->getDoctrine()->getManager();
    $repository = $entityManager->getRepository(Hgv::class);
    $data = $repository->findBy(array('tmNr' => $tm, 'texLett' => $texLett), array('mehrfachKennung' => 'ASC'));

    return $this->render('shortcut/shortcut.html.twig', array('data' => $data));
  }

  public function tm($id): Response
  {
    $entityManager = $this->getDoctrine()->getManager();
    $repository = $entityManager->getRepository(Hgv::class);
    $data = $repository->findBy(array('tmNr' => $id), array('texLett' => 'ASC', 'mehrfachKennung' => 'ASC'));

    return $this->render('shortcut/shortcut.html.twig', array('data' => $data));
  }

  public function ddb($id): Response
  {
    $ddb = explode(';', $id); // routing requirements make sure that there are either two or five »;«
    $entityManager = $this->getDoctrine()->getManager();
    $repository = $entityManager->getRepository(Hgv::class);

    $criteria = array();
    $layout = 'base';
    if(count($ddb) === 3){
      $criteria = array('ddbSer' => $ddb[0], 'ddbVol' => $ddb[1], 'ddbDoc' => $ddb[2]);
    } else if (count($ddb) === 6){
      $criteria = array('publikation' => $ddb[0], 'band' => $ddb[1], 'zusBand' => $ddb[2], 'nummer' => $ddb[3], 'seite' => $ddb[4], 'zusaetzlich' => $ddb[5]);
      $layout = 'plain';
    }

    $data = $repository->findBy($criteria, array('texLett' => 'ASC', 'mehrfachKennung' => 'ASC'));

    return $this->render('shortcut/shortcut.html.twig', array('data' => $data, 'layout' => $layout));
  }
}
