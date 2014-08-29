<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BrowseController extends HgvController
{
    const TYPE_SINGLE   = 'table';
    const TYPE_MULTIPLE = 'formular';
    const TYPE_SEARCH   = 'search';
    const COUNT_TRUE    = true;
    const COUNT_FALSE   = false;
    
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
      'url'                => 'Link',
      'chronMinimum'       => 'Chron-Minimum',
      'chronMaximum'       => 'Chron-Maximum',
      'chronGlobal'        => 'Chron-Global',
      'uebersetzungen'     => 'Übersetzungen'
    );

    static $TABLE_MAP = array(
      'publikation'        => 'h',
      'publikationLang'    => 'h',
      'band'               => 'h',
      'nummer'             => 'h',
      'tmNr'               => 'h',
      'jahr'               => 'h',
      'monat'              => 'h',
      'tag'                => 'h',
      'jahrIi'             => 'h',
      'jh'                 => 'h',
      'jhIi'               => 'h',
      'ort'                => 'h',
      'originaltitel'      => 'h',
      'material'           => 'h',
      'abbildung'          => 'h',
      'anderePublikation'  => 'h',
      'bemerkungen'        => 'h',
      'inhalt'             => 'h',
      'url'                => 'p',
      'chronMinimum'       => 'h',
      'chronMaximum'       => 'h',
      'chronGlobal'        => 'h',
      'uebersetzungen'     => 'h'
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
      'lt'  => 'Kleiner',
      'lte' => 'Kleiner oder gleich',
      'gt'  => 'Größer',
      'gte' => 'Größer oder gleich'
    );
    
    static $SQL_OPERATOR_MAP =  array(
        'sp'  => 'SPLITTERSUCHE',
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

    /**
     * Show search form
     * **/
    public function searchAction()
    {
      return $this->render('PapyrillioHgvBundle:Browse:search.html.twig', array('fieldList' => self::$FIELD_LIST_SEARCH));
    }

    /***
     * Show single data record
     * */
    public function singleAction(){
      $search = $this->getParameterSearch(); // var_dump($search);
      $sort   = $this->getParameterSort();   // var_dump($sort);
      $show   = $this->getParameterShow();   // var_dump($show);

      $result = $this->getResult(array_merge($search, $show), $sort);

      try{

        $result = $this->getResult(array_merge($search, $show), $sort);
        $record = null;
        if(count($result['data'])){
          $record = $result['data'][0];
        }
        

        return $this->render('PapyrillioHgvBundle:Browse:single.html.twig', array(
          'search'             => $search,
          'sort'               => $sort,
          'show'               => $show,
          'showPrev'           => ($show['skip'] > 0 ? array_merge($show, array('skip' => $show['skip'] - 1)) : null),
          'showNext'           => ($show['skip'] < ($result['countSearch'] - 1) ? array_merge($show, array('skip' => $show['skip'] + 1)) : null),
          'fieldList'          => self::$FIELD_LIST_SINGLE,
          'countTotal'         => $result['countTotal'],
          'countSearch'        => $result['countSearch'],
          'record'             => $record));
      } catch(Exception $e){
        return $this->render('PapyrillioHgvBundle:Catalogue:error.html.twig', array('message' => 'FileMaker Error #' . $e->getMessage()));
      }
    }

    /**
     * Show result table for current (latest) search
     * **/
    public function multipleAction(){
      $search = $this->getParameterSearch();
      $sort   = $this->getParameterSort();
      $show   = $this->getParameterShow();

      try{
        $result = $this->getResult($search, $sort);
        $this->setSessionParameter('search', $search); // only a successfull search will save search and sort parameters to session
        $this->setSessionParameter('sort', $sort);

        $sortLinkParameters = array();
        $sortDirections = array();
        foreach(self::$FIELD_LIST_MULTIPLE as $key => $caption){
          $sortDirections[$key] = null;

          $tmpKey = $key;
          /*if($key == 'DatierungIi'){
            $tmpKey = 'ChronGlobal';
          } else if($key == 'PublikationLang'){
            $tmpKey = 'Publikation';
          } */
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
          'result' => $result['data'],
          'search' => $search,
          'sort' => $sort,
          'sortDirections' => $sortDirections,
          'sortLinkParameters' => $sortLinkParameters,
          'show' => $show,
          'from' => $search['skip'] + 1,
          'to' => $search['skip'] + count($result['data']),
          'countTotal' => $result['countTotal'],
          'countSearch' => $result['countSearch'],
          'searchPrev' => ($search['skip'] > 0 ? array_merge($search, array('skip' => max(0, $search['skip'] - $search['max']))) : null),
          'searchNext' => ($search['skip'] + $search['max'] < $result['countSearch'] ? array_merge($search, array('skip' => min($result['countSearch'] / $search['max'] * $search['max'], $search['skip'] + $search['max']))) : null),
          'fieldList' => self::$FIELD_LIST_MULTIPLE,
          'query' => $result['query']
        ));
      } catch(Exception $e){
        return $this->render('PapyrillioHgvBundle:Catalogue:error.html.twig', array('message' => 'Error: ' . $e->getMessage()));
      }
    }

    protected function getResult($search, $sort){
      $orderBy = $this->orderBy($sort);
      list($where, $parameters) = $this->where($search);

      $entityManager = $this->getDoctrine()->getEntityManager();
      $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');

      $select = $this->select($search, $sort);
      $selectCount = $this->select($search, $sort, self::COUNT_TRUE);

      $countTotal  = $entityManager->createQuery($selectCount)->getSingleScalarResult();
      $countSearch = $entityManager->createQuery($selectCount . $where)->setParameters($parameters)->getSingleScalarResult();
      $result      = $entityManager->createQuery($select . $where . $orderBy)->setParameters($parameters)->setMaxResults($search['max'])->setFirstResult($search['skip'])->getResult();

      $query = $select . $where . $orderBy;
      foreach($parameters as $key => $value){
        $query = str_replace(':' . $key, $value, $query);
      }

      return array('data' => $result, 'countTotal' => $countTotal, 'countSearch' => $countSearch, 'query' => $query);
    }

    /*
     * Generate SELECT and FROM clauses for doctrine query
     * 
     * */
    protected function select($search, $sort, $count = self::COUNT_FALSE){
      // select fields
      $select = $count ? 'SELECT COUNT(DISTINCT h.id) ' : 'SELECT DISTINCT h ';
      
      // choose tables
      $select .= 'FROM PapyrillioHgvBundle:Hgv h LEFT JOIN h.mentionedDates m ';

      $pictureLinks = false;
      foreach($sort as $sortItem){
        if(self::$TABLE_MAP[$sortItem['key']] === 'p'){
          $pictureLinks = true;
          break;
        }
      }
      
      foreach($search['criteria'] as $field => $criterion){
        if(self::$TABLE_MAP[$field] === 'p'){
          $pictureLinks = true;
          break;
        }
      }
      
      if($pictureLinks){
        $select .= 'LEFT JOIN h.pictureLinks p ';
      }

      return $select;
    }

    /*
     * Generate WHERE clause for doctrine query
     * 
     * */
    protected function where($search){
      $where = '';
      $parameters = array();
      if(count($search['criteria'])){
        $where = ' WHERE ';
        $operator = ' ' . $search['operator'] . ' ';
        foreach($search['criteria'] as $field => $criterion){
          if(self::$SQL_OPERATOR_MAP[$criterion['operator']] === 'SPLITTERSUCHE'){
            $where .= '(';
            foreach(explode(' ', $criterion['value']) as $splinterIndex => $splinterValue){
              $where .= 'h.' . $field . ' LIKE :' . $field . $splinterIndex . ' AND ';
              $parameters[$field . $splinterIndex] = '%' . $splinterValue . '%';
            }
            $where = rtrim($where, ' AND ') . ')' . $operator;
          } else if(in_array($field, array('jahr', 'monat', 'tag', 'jh', 'jahrIi', 'monatIi', 'tagIi', 'jhI2', 'chronMinimum', 'chronMaximum', 'chronGlobal')) and preg_match('/^(-?\d+)\.+(-?\d+)$/', $criterion['value'], $matches)){ // VALUE1...VALUE2
            if($search['mentionedDates'] === 'with'){
              $where .= '((h.' . $field . ' >= :' . $field . 'Von AND h.' . $field . ' <= :' . $field . 'Bis) OR (m.' . $field . ' >= :' . $field . 'Von AND m.' . $field . ' <= :' . $field . 'Bis))' . $operator;
              $parameters[$field . 'Von'] = $matches[1];
              $parameters[$field . 'Bis'] = $matches[2];
            } else if($search['mentionedDates'] === 'only') {
              $where .= '(m.' . $field . ' >= :' . $field . 'Von AND m.' . $field . ' <= :' . $field . 'Bis)' . $operator;
              $parameters[$field . 'Von'] = $matches[1];
              $parameters[$field . 'Bis'] = $matches[2];
            } else {
              $where .= '(h.' . $field . ' >= :' . $field . 'Von AND h.' . $field . ' <= :' . $field . 'Bis)' . $operator;
              $parameters[$field . 'Von'] = $matches[1];
              $parameters[$field . 'Bis'] = $matches[2];
            }
          } else if ($criterion['value'] === '*') {
            //echo '*-Suche für Feld "' . $field .'"';
            $where .= '(' . self::$TABLE_MAP[$field] . '.' . $field . ' IS NOT NULL AND ' . self::$TABLE_MAP[$field] . '.' . $field . ' <> :' . $field . ')' . $operator;
            $parameters[$field] = '';
          } else if ($criterion['value'] === '=') {
            //echo '=-Suche für Feld "' . $field .'"';
            $where .= '(' . self::$TABLE_MAP[$field] . '.' . $field . ' IS NULL OR ' . self::$TABLE_MAP[$field] . '.' . $field . ' = :' . $field . ')' . $operator;
            $parameters[$field] = '';
          } else {
            if(in_array($field, array('jahr', 'monat', 'tag', 'jh', 'erg', 'jahrIi', 'monatIi', 'tagIi', 'jhIi', 'ergIi', 'chronMinimum', 'chronMaximum', 'chronGlobal', 'datierung', 'datierungIi', 'unsicher'))){
              if($search['mentionedDates'] == 'with'){
                $where .= '((h.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . ') OR (m.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . '))' . $operator;
              } else if($search['mentionedDates'] == 'only') {
                $where .= 'm.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . $operator;
              } else {
                $where .= 'h.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . $operator;
              }
            } else {
              $where .= self::$TABLE_MAP[$field] . '.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . $operator;
            }
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
      return array($where, $parameters);
   }

    /* 
     * Generate ORDER BY clause for doctrine query
     * 
     * */
    protected function orderBy($sort){
      if(count($sort)){
        $orderBy = ' ORDER BY ';
        foreach ($sort as $sortItem) {
          $direction = substr($sortItem['direction'], 0, strpos($sortItem['direction'], 'c') + 1);
          if($sortItem['key'] == 'datierungIi'){
            $orderBy .=  'h.chronGlobal ' . $direction . ', h.monat ' . $direction . ', h.tag ' . $direction . ', ';
          } else if($sortItem['key'] == 'publikationLang') {
            $orderBy .= 'h.publikation ' . $direction . ', h.band ' . $direction . ', h.zusBand ' . $direction . ', h.nummer ' . $direction;
          } else {
            $orderBy .= self::$TABLE_MAP[$sortItem['key']] . '.' . $sortItem['key'] . ' ' . $direction . ', ';
          }
        }
        return rtrim($orderBy, ', ');
      }
      return '';
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

        // default options        
        if(!isset($search['mentionedDates']) || !in_array($search['mentionedDates'], array('with', 'without', 'only'))){
          $search['mentionedDates'] = 'without';
        }

        // default paginator
        if(!isset($search['skip']) or !is_int($search['skip'] + 0)){
          $search['skip'] = 0;
        }

        if(!isset($search['max']) or !is_int($search['max'] + 0)){
          $search['max'] = 100;
        }

        return $search;
      } else if($search = $this->getSessionParameter('search')){ // try to retrieve from session
        return $search;
      }
      return array('criteria' => array(), 'operator' => 'and', 'skip' => 0, 'max' => 100, 'mentionedDates' => false); // return default search parameters
    }

    protected function getParameterSort(){
      // URL PARAMETER
      if($sortList = $this->getParameter('sort')){
        $finalSortingOrder = array();
        $finalSortingIndex = 1;
        foreach($sortList as $sort){
          if($sort['key'] == 'Datierung2'){
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'chronGlobal', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'monat', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'tag', 'direction' => $sort['direction']);
          } elseif ($sort['key'] == 'PublikationL') {
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'publikation', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'band', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'zusBand', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'nummer', 'direction' => $sort['direction']);
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'seite', 'direction' => $sort['direction']);
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
        1 => array('key' => 'chronGlobal', 'direction' => 'ascend'),
        2 => array('key' => 'monat', 'direction' => 'descend'),
        3 => array('key' => 'tag', 'direction' => 'descend')
      );
    }

}
