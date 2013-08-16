<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BrowseController extends HgvController
{
    const TYPE_SINGLE = 'table';
    const TYPE_MULTIPLE = 'formular';
    const TYPE_SEARCH = 'search';
    
    static $FIELD_LIST_SEARCH = array(
      'publikation'        => 'Publikation',
      'band'               => 'Band',
      'nummer'             => 'Nummer',
      'tmNr'               => 'TM Nr.',
      'jahr'               => 'Jahr',
      'monat'              => 'Monat',
      'tag'                => 'Tag',
      'jahrIi'             => 'Jahr (2)',
      'jh'                 => 'Jahrhundert',
      'jhIi'               => 'Jahrhundert (2)',
      'ort'                => 'Ort',
      'originaltitel'      => 'Originaltitel',
      'material'           => 'Material',
      'abbildung'          => 'Abbildung',
      'anderePublikation'  => 'Andere Publikation',
      'bemerkungen'        => 'Bemerkungen',
      'inhalt'             => 'Inhalt',
      'linkFm'             => 'Link',
      'chronMinimum'       => 'Chron-Minimum',
      'chronMaximum'       => 'Chron-Maximum',
      'chronGlobal'        => 'Chron-Global',
      'uebersetzungen'     => 'Übersetzungen'
    );

    static $FIELD_LIST_SINGLE = array(
      'publikationLang'    => 'Publikation',
      'datierungIi'        => 'Datierung',
      'ort'                => 'Ort',
      'originaltitelHtml'  => 'Originaltitel',
      'material'           => 'Material',
      'abbildung'          => 'Abbildung',
      'ddbVol'             => 'Texte der DDBDP'
    );
    
    static $FIELD_LIST_MULTIPLE = array(
      'publikationLang'    => 'Publikation',
      'datierungIi'        => 'Datierung',
      'ort'                => 'Ort',
      'originaltitelHtml'  => 'Originaltitel'
    );
    
    static $OPERATOR_LIST = array(
      'cn'  => 'Enthält',
      'bw'  => 'Beginnt mit',
      'ew'  => 'Endet mit',
      'eq'  => 'Ist gleich',
      'neq' => 'Ist ungleich',
      'lt'  => 'Kleiner als',
      'lte' => 'Kleiner als oder gleich',
      'gt'  => 'Größer als',
      'gte' => 'Größer als oder gleich'
    );
    
    static $SQL_OPERATOR_MAP =  array(
        'cn'  => 'LIKE',
        'bw'  => 'LIKE',
        'ew'  => 'LIKE',
        'eq'  => '=',
        'neq' => '<>',
        'lt'  => '<',
        'lte' => '<=',
        'gt'  => '>',
        'gte' => '>='
      );

    public function multipleAction(){
      $search = $this->getParameterSearch();  var_dump($search);
      $sort   = $this->getParameterSort();   // var_dump($sort);
      $show   = $this->getParameterShow();   // var_dump($show);

      $where = '';
      $orderBy = '';
      $parameters = array();
      
      if(count($search['criteria'])){
        $where = ' WHERE ';
        $operator = ' ' . $search['operator'] . ' ';
        foreach($search['criteria'] as $field => $criterion){

          if($field == 'jahr' and preg_match('/^(\d+)\.+(\d+)$/', $criterion['value'], $matches)){ // YEAR...YEAR2
            $where .= '(h.jahr >= :jahrVon AND h.jahr <= :jahrBis)' . $operator;
            $parameters['jahrVon'] = $matches[1];
            $parameters['jahrBis'] = $matches[2];
          } else {
            $where .= 'h.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . $operator;
            
            switch ($criterion['operator']) {
              case 'cn':
                $parameters[$field] = '%' . $criterion['value'] . '%';
                break;
              case 'bw':
                $parameters[$field] = $criterion['value'] . '%';
                break;
              case 'ew':
                $parameters[$field] = '%' . $criterion['value'];
                break;
              default:
                $parameters[$field] = $criterion['value'];
                break;
            }
          }

        }
        $where = preg_replace('/' . $operator . '$/', '', $where);
      }

      if(count($sort)){
        $orderBy = ' ORDER BY ';
        foreach ($sort as $sortItem) {
          $direction = substr($sortItem['direction'], 0, strpos($sortItem['direction'], 'c') + 1);
          if($sortItem['key'] == 'datierungIi'){
            $orderBy .= 'h.chronGlobal ' . $direction . ', h.monat ' . $direction . ', h.tag ' . $direction . ', ';
          } else if($sortItem['key'] == 'publikationLang') {
            $orderBy .= 'h.publikation ' . $direction . ', h.band ' . $direction . ', h.zusBand ' . $direction . ', h.nummer ' . $direction;
          } else {
            $orderBy .= 'h.' . $sortItem['key'] . ' ' . $direction . ', ';
          }
        }
        $orderBy = rtrim($orderBy, ', ');
      }
      echo $where . $orderBy;
      
      $entityManager = $this->getDoctrine()->getEntityManager();
      $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');

      try{
        $countTotal  = $entityManager->createQuery('SELECT COUNT(h.id) FROM PapyrillioHgvBundle:Hgv h')->getSingleScalarResult();
        $countSearch = $entityManager->createQuery('SELECT COUNT(h.id) FROM PapyrillioHgvBundle:Hgv h' . $where)->setParameters($parameters)->getSingleScalarResult();
        $result      = $entityManager->createQuery('SELECT h FROM PapyrillioHgvBundle:Hgv h ' . $where . $orderBy)->setParameters($parameters)->setMaxResults($search['max'])->setFirstResult($search['skip'])->getResult();
        $this->setSessionParameter('search', $search); // only a successfull search will save search and sort parameters to session
        $this->setSessionParameter('sort', $sort);

        $sortLinkParameters = array();
        $sortDirections = array();
        foreach(self::$FIELD_LIST_MULTIPLE as $key => $caption){
          $sortDirections[$key] = null;

          $tmpKey = $key;
          if($key == 'DatierungIi'){
            $tmpKey = 'ChronGlobal';
          } else if($key == 'PublikationLang'){
            $tmpKey = 'Publikation';
          } 

          $direction = 'ascend';
          foreach($sort as $sortItem){
            if($sortItem['key'] == $tmpKey){
              if($sortItem['direction'] == $direction){
                $direction = 'descend';
              }
              $sortDirections[$key] = $direction == 'ascend' ? 'descend' : 'ascend';
            }
          }
          $sortLinkParameters[$key] = array(1 => array('key' => $key, 'direction' => $direction));
        }

        return $this->render('PapyrillioHgvBundle:Browse:multi.html.twig', array(
          'result' => $result,
          'search' => $search,
          'sort' => $sort,
          'sortDirections' => $sortDirections,
          'sortLinkParameters' => $sortLinkParameters,
          'show' => $show,
          'from' => $search['skip'] + 1,
          'to' => $search['skip'] + count($result),
          'countTotal' => $countTotal,
          'countSearch' => $countSearch,
          'searchPrev' => ($search['skip'] > 0 ? array_merge($search, array('skip' => max(0, $search['skip'] - $search['max']))) : null),
          'searchNext' => ($search['skip'] + $search['max'] < $countSearch ? array_merge($search, array('skip' => min($countSearch / $search['max'] * $search['max'], $search['skip'] + $search['max']))) : null),
          'fieldList' => self::$FIELD_LIST_MULTIPLE
        ));
      } catch(Exception $e){
        return $this->render('PapyrillioHgvBundle:Catalogue:error.html.twig', array('message' => 'Error: ' . $e->getMessage()));
      }
    }

    public function searchAction()
    {
      return $this->render('PapyrillioHgvBundle:Browse:search.html.twig', array('fieldList' => self::$FIELD_LIST_SEARCH));
    }

    protected function getParameterShow(){
      if($show = $this->getParameter('show')){ // try to retrieve vom post or get
        return $show;
      } else if($show = $this->getSessionParameter('show')){ // try to retrieve from session
        return $show;
      }
      return array('skip' => 0, 'max' => 1); // default 
    }

    protected function getParameterSearch(){
      if($search = $this->getParameter('search')){ // try to retrieve vom post or get

        // get rid of empty filter items
        if(isset($search['criteria'])){
          foreach($search['criteria'] as $key => $criterion){
            $value = trim($criterion['value']);
            if(!empty($value)){
              $search['criteria'][$key]['value'] = $value;
            } else {
              unset($search['criteria'][$key]);
            }
          }
        } else {
          $search['criteria'] = array();
        }

        // default operator
        if(!isset($search['operator']) or !in_array($search['operator'], array('and', 'or'))){
          $search['operator'] = 'and';
        }

        // default paginator
        if(!isset($search['skip']) or !is_int($search['skip'] + 0)){
          $search['skip'] = 0;
        }

        if(!isset($search['max']) or !is_int($search['max'] + 0)){
          $search['max'] = 100;
        }

        return $search;
      }
      return $this->getSessionParameter('search'); // try to retrieve from session
    }

    protected function getParameterSort(){
      // URL PARAMETER
      if($sortList = $this->getParameter('sort')){
        $finalSortingOrder = array();
        $finalSortingIndex = 1;
        foreach($sortList as $sort){
          if($sort['key'] == 'Datierung2'){
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'ChronGlobal', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'M', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'T', 'direction' => $sort['direction']);
          } elseif ($sort['key'] == 'PublikationL') {
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'Publikation', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'Band', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'Zus.Band', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'Nummer', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'Seite', 'direction' => $sort['direction']);
          } elseif(!empty($sort['key'])){
            $finalSortingOrder[$finalSortingIndex++] = $sort;
          }
        }

        if(count($finalSortingOrder)){
          return $finalSortingOrder;
        }
      }

      // SESSION
      if($sortList = $this->getSessionParameter('sort')){
        return $sortList;
      }

      // DEFAULT
      return array(
        1 => array('key' => 'ChronGlobal', 'direction' => 'ascend'),
        2 => array('key' => 'M', 'direction' => 'descend'),
        3 => array('key' => 'T', 'direction' => 'descend')
      );
    }

}
