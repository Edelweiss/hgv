<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ShortcutController extends HgvController
{
  public function hgvAction($id)
  {
    $tm = preg_replace('/[^\d]+/', '', $id);
    $texLett = preg_replace('/\d+/', '', $id);
    
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');
    $data = $repository->findBy(array('tmNr' => $tm, 'texLett' => $texLett), array('mehrfachKennung' => 'ASC'));

    return $this->render('PapyrillioHgvBundle:Shortcut:shortcut.html.twig', array('data' => $data));
  }

  public function tmAction($id)
  {
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');
    $data = $repository->findBy(array('tmNr' => $id), array('texLett' => 'ASC', 'mehrfachKennung' => 'ASC'));

    return $this->render('PapyrillioHgvBundle:Shortcut:shortcut.html.twig', array('data' => $data));
  }

  public function ddbAction($id)
  {
    $ddb = explode(';', $id); // routing requirement makes sure that there are two »;«
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');
    $data = $repository->findBy(array('ddbSer' => $ddb[0], 'ddbVol' => $ddb[1], 'ddbDoc' => $ddb[2]), array('texLett' => 'ASC', 'mehrfachKennung' => 'ASC'));

    return $this->render('PapyrillioHgvBundle:Shortcut:shortcut.html.twig', array('data' => $data));
  }
}
