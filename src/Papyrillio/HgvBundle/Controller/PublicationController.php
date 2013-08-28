<?php

namespace Papyrillio\HgvBundle\Controller;
use Symfony\Component\HttpFoundation\Response;
use Papyrillio\HgvBundle\Entity\Publication;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class PublicationController extends HgvController
{
  public function indexAction(){
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Publication');

    $publications = $entityManager->createQuery('SELECT p FROM PapyrillioHgvBundle:Publication p GROUP BY p.collection, p.volume ORDER BY p.collection, p.volume')->getResult();
    $reprints = $entityManager->createQuery('SELECT COUNT(p.id) countCollectionItems, COUNT(p.parent) AS countReprint, p.collection, p.volume, p.particle FROM PapyrillioHgvBundle:Publication p GROUP BY p.collection, p.volume, p.particle HAVING countCollectionItems = countReprint')->getResult();

    Publication::setReprints($reprints);
    return $this->render('PapyrillioHgvBundle:Publication:index.html.twig', array(
      'publications' => $publications
    ));
  }

  public function loadResultsAction($collection = null, $volume = null, $particle = null, $number = null, $side = null, $extra = null){
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');

    $query = 'SELECT p FROM PapyrillioHgvBundle:Hgv p WHERE p.publikation = :publikation';
    $parameters = array('publikation' => $collection);

    $where = $this->getWhere(array('band' => $volume, 'zusBand' => $particle, 'nummer' => $number, 'seite' => $side, 'zusaetzlich' => $extra));
    $query .= $where['query'];
    $parameters = array_merge($parameters, $where['parameters']);
    
    $query .= ' ORDER BY p.nummer, p.seite, p.zusaetzlich';

    $publications = $entityManager->createQuery($query)->setParameters($parameters)->getResult();

    return $this->render('PapyrillioHgvBundle:Publication:loadResults.html.twig', array('publications' => $publications));
  }

  public function loadNumbersAction($collection = null, $volume = null, $particle = null){
    $entityManager = $this->getDoctrine()->getEntityManager();
    $repository = $entityManager->getRepository('PapyrillioHgvBundle:Publication');

    $query = 'SELECT p FROM PapyrillioHgvBundle:Publication p WHERE p.collection = :collection';
    $parameters = array('collection' => $collection);

    $where = $this->getWhere(array('volume' => $volume, 'particle' => $particle));
    $query .= $where['query'];
    $parameters = array_merge($parameters, $where['parameters']);

    $query .= ' GROUP BY p.number, p.side, p.extra ORDER BY p.number, p.side, p.extra';

    $publications = $entityManager->createQuery($query)->setParameters($parameters)->getResult();

    return $this->render('PapyrillioHgvBundle:Publication:loadNumbers.html.twig', array('publications' => $publications));
  }

  public function getWhere($where){
    $query = '';
    $parameters = array();

    foreach($where as $key => $value){
      
      if($value !== NULL){
        $query .= ' AND p.' . $key . ' = :' . $key;
        $parameters[$key] = $value;
      } else {
        $query .=  " AND (p." . $key . " = '' OR p." . $key . " IS NULL)";
      }
    }

    return array('query' => $query, 'parameters' => $parameters);
  }

}
