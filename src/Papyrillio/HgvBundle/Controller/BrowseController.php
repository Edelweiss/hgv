<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BrowseController extends HgvController
{
    const TYPE_SINGLE   = 'table';
    const TYPE_MULTIPLE = 'formular';
    const TYPE_SEARCH   = 'search';
    
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
        $record = $result['data'][0];
        $translations = array();
        $original = array('in: ', '&amp;', '&quot;', '&lt;', '&gt;');
        $mask = array('#INCOLONSPACE#', '#QUOTATIONMARK#', '#LESSTHAN#', '#GREATERTHAN#');
        $canonical = array('in: ', ' & ', '"', '<', '>');
        $uebersetzungen = str_replace($original, $mask, $record->getUebersetzungen());

        if(preg_match_all('/(([^: ]+): )([^:]+([ \.$\d]|$))/', $uebersetzungen, $matches)){
          if(count($matches[0])){
            foreach ($matches[2] as $index => $language) {
              $translations[$language] = array();
              foreach(explode(';', $matches[3][$index]) as $translation){

                $translations[$language][] = str_replace($mask, $canonical, $translation);
              }
            }
          }
        }

        return $this->render('PapyrillioHgvBundle:Browse:single.html.twig', array(
          'search'             => $search,
          'sort'               => $sort,
          'show'               => $show,
          'showPrev'           => ($show['skip'] > 0 ? array_merge($show, array('skip' => $show['skip'] - 1)) : null),
          'showNext'           => ($show['skip'] < ($result['countSearch'] - 1) ? array_merge($show, array('skip' => $show['skip'] + 1)) : null),
          'fieldList'          => self::$FIELD_LIST_SINGLE,
          'translations'       => $translations,
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
      $where = '';
      $orderBy = '';
      $parameters = array();

      if(count($search['criteria'])){
        $where = ' WHERE ';
        $operator = ' ' . $search['operator'] . ' ';
        foreach($search['criteria'] as $field => $criterion){
          if($field == 'jahr' and preg_match('/^(\d+)\.+(\d+)$/', $criterion['value'], $matches)){ // YEAR...YEAR2
            if($search['mentionedDates'] === 'with'){
              $where .= '((h.jahr >= :jahrVon AND h.jahr <= :jahrBis) OR (m.jahr >= :jahrVon AND m.jahr <= :jahrBis))' . $operator;
              $parameters['jahrVon'] = $matches[1];
              $parameters['jahrBis'] = $matches[2];
            } else if($search['mentionedDates'] === 'only') {
              $where .= '(m.jahr >= :jahrVon AND m.jahr <= :jahrBis)' . $operator;
              $parameters['jahrVon'] = $matches[1];
              $parameters['jahrBis'] = $matches[2];
            } else {
              $where .= '(h.jahr >= :jahrVon AND h.jahr <= :jahrBis)' . $operator;
              $parameters['jahrVon'] = $matches[1];
              $parameters['jahrBis'] = $matches[2];
            }
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
              $where .= 'h.' . $field . ' ' . self::$SQL_OPERATOR_MAP[$criterion['operator']] . ' :' . $field . $operator;
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

      $entityManager = $this->getDoctrine()->getEntityManager();
      $repository = $entityManager->getRepository('PapyrillioHgvBundle:Hgv');

      $select = 'SELECT DISTINCT h FROM PapyrillioHgvBundle:Hgv h LEFT JOIN h.mentionedDates m ';
      $countTotal  = $entityManager->createQuery('SELECT COUNT(DISTINCT h.id) FROM PapyrillioHgvBundle:Hgv h LEFT JOIN h.mentionedDates m')->getSingleScalarResult();
      $countSearch = $entityManager->createQuery('SELECT COUNT(DISTINCT h.id) FROM PapyrillioHgvBundle:Hgv h LEFT JOIN h.mentionedDates m' . $where)->setParameters($parameters)->getSingleScalarResult();
      $result      = $entityManager->createQuery($select . $where . $orderBy)->setParameters($parameters)->setMaxResults($search['max'])->setFirstResult($search['skip'])->getResult();
      
      $query = $select . $where . $orderBy;
      foreach($parameters as $key => $value){
        $query = str_replace(':' . $key, $value, $query);
      }

      return array('data' => $result, 'countTotal' => $countTotal, 'countSearch' => $countSearch, 'query' => $query);
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
