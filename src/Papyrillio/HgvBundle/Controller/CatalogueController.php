<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FileMaker_Error;

class CatalogueController extends HgvController
{
    const TYPE_LIST = 'table';
    const TYPE_SHOW = 'formular';

    public function indexAction()
    {
        return $this->render('PapyrillioHgvBundle:Catalogue:index.html.twig');
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
        if(!isset($search['operator']) or !in_array($search['operator'], array(FILEMAKER_FIND_AND, FILEMAKER_FIND_OR))){
          $search['operator'] = FILEMAKER_FIND_AND;
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
            $finalSortingOrder[$finalSortingIndex++] = array('key' => 'ChronGlobal_Static', 'direction' => $sort['direction']);
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
        1 => array('key' => 'ChronGlobal_Static', 'direction' => FILEMAKER_SORT_ASCEND),
        2 => array('key' => 'M', 'direction' => FILEMAKER_SORT_ASCEND),
        3 => array('key' => 'T', 'direction' => FILEMAKER_SORT_ASCEND)
      );
    }

    public function listAction()
    {
      $fm = $this->get('papyrillio_hgv.file_maker_hgv');

      $filterList = $this->getParameterSearch();
      $sortList = $this->getParameterSort();

      $result = $fm->search($filterList, $sortList);

      if($fm->isError($result)){
        return $this->render('PapyrillioHgvBundle:Catalogue:error.html.twig', array('message' => 'FileMaker Error #' . $result->code . ': ' . $result->getMessage()));
      } else {
        $this->setSessionParameter('search', $filterList); // only a successfull search will save search and sort parameters to session
        $this->setSessionParameter('sort', $sortList);
        
        $sortLinkParameters = array();
        $sortDirections = array();
        foreach(self::getFieldList() as $key => $caption){
          $sortDirections[$key] = null;

          $tmpKey = $key;
          if($key == 'Datierung2'){
            $tmpKey = 'ChronGlobal_Static';
          } else if($key == 'PublikationL'){
            $tmpKey = 'Publikation';
          } 

          $direction = FILEMAKER_SORT_ASCEND;
          foreach($sortList as $sort){
            if($sort['key'] == $tmpKey){
              if($sort['direction'] == $direction){
                $direction = FILEMAKER_SORT_DESCEND;
              }
              $sortDirections[$key] = $direction == FILEMAKER_SORT_ASCEND ? FILEMAKER_SORT_DESCEND : FILEMAKER_SORT_ASCEND;
            }
          }
          $sortLinkParameters[$key] = array(1 => array('key' => $key, 'direction' => $direction));
        }
        
        return $this->render('PapyrillioHgvBundle:Catalogue:list.html.twig', array(
          'search'             => $filterList,
          'searchPrev'         => ($filterList['skip'] > 0 ? array_merge($filterList, array('skip' => max(0, $filterList['skip'] - $filterList['max']))) : null),
          'searchNext'         => ($filterList['skip'] + $filterList['max'] < $result->getFoundSetCount() ? array_merge($filterList, array('skip' => min($result->getFoundSetCount() / $filterList['max'] * $filterList['max'], $filterList['skip'] + $filterList['max']))) : null),
          'sort'               => $sortList,
          'sortLinkParameters' => $sortLinkParameters,
          'sortDirections'     => $sortDirections,
          'fieldList'          => self::getFieldList(),
          'operatorSymbolList' => self::getOperatorSymbolList(),
          'operatorList'       => self::getOperatorList(),
          'from'               => $filterList['skip'] + 1,
          'to'                 => min($result->getFetchCount(), $filterList['max']),
          'result'             => $result));
      }
    }

    public function showAction()
    {
      $fm = $this->get('papyrillio_hgv.file_maker_hgv');

      if($search = $this->getParameterSearch()){ // SEARCH

        $sort = $this->getParameterSort();
        $show = $this->getParameterShow();
        $result = $fm->search(array_merge($search, $show), $sort);

        if($fm->isError($result)){
          return $this->render('PapyrillioHgvBundle:Catalogue:error.html.twig', array('message' => 'FileMaker Error #' . $result->code . ': ' . $result->getMessage()));
        } else {

          $translations = array();
          $original = array('in: ', '&amp;', '&quot;', '&lt;', '&gt;');
          $mask = array('#INCOLONSPACE#', '#QUOTATIONMARK#', '#LESSTHAN#', '#GREATERTHAN#');
          $canonical = array('in: ', ' & ', '"', '<', '>');
          $uebersetzungen = str_replace($original, $mask, $result->getFirstRecord()->getField('Uebersetzungen'));

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

          return $this->render('PapyrillioHgvBundle:Catalogue:show.html.twig', array(
            'search'             => $search,
            'sort'               => $sort,
            'show'               => $show,
            'showPrev'           => ($show['skip'] > 0 ? array_merge($show, array('skip' => $show['skip'] - 1)) : null),
            'showNext'           => ($show['skip'] < ($result->getFoundSetCount() - 1) ? array_merge($show, array('skip' => $show['skip'] + 1)) : null),
            'fieldList'          => self::getFieldList(self::TYPE_SHOW),
            'translations'       => $translations,
            'result'             => $result));
        }
      } else {
        $result = $fm->pick();

        return $this->render('PapyrillioHgvBundle:Catalogue:show.html.twig', array(
            'search'             => $search,
            'show'               => null,
            'showPrev'           => null,
            'showNext'           => null,
            'fieldList'          => self::getFieldList(self::TYPE_SHOW), 
            'result'             => $result));
      }
    }

    public function searchAction()
    {
      return $this->render('PapyrillioHgvBundle:Catalogue:search.html.twig', array('fieldList' => self::getSearchList()));
    }

    protected static function getSearchList(){
      return array(
          'Publikation'        => 'Publikation',
          'Band'               => 'Band',
          'Nummer'             => 'Nummer',
          'TM_Nr.'             => 'TM Nr.',
          'J'                  => 'Jahr',
          'M'                  => 'Monat',
          'T'                  => 'Tag',
          'J2'                 => 'Jahr (2)',
          'Jh'                 => 'Jahrhundert',
          'Jh2'                => 'Jahrhundert (2)',
          'Ort'                => 'Ort',
          'Originaltitel'      => 'Originaltitel',
          'Material'           => 'Material',
          'Abbildung'          => 'Abbildung',
          'Andere Publikation' => 'Andere Publikation',
          'Bemerkungen'        => 'Bemerkungen',
          'Inhalt'             => 'Inhalt',
          'Link1FM'            => 'Link',
          'ChronMinimum'       => 'Chron-Minimum',
          'ChronMaximum'       => 'Chron-Maximum',
          'ChronGlobal_Static'        => 'Chron-Global',
          'Uebersetzungen'     => 'Übersetzungen'
        );
    }

    protected static function getOperatorList(){
      return array(
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
    }

    protected static function getOperatorSymbolList(){
      return array(
        'cn'  => '*=',
        'bw'  => '^=',
        'ew'  => '$=',
        'eq'  => '==',
        'neq' => '!=',
        'lt'  => '<',
        'lte' => '<=',
        'gt'  => '>',
        'gte' => '>=',
        'or'  => '||',
        'and' => '&&'
      );
    }
    
    protected static function getFieldList($type = null){
      switch($type){
        case self::TYPE_SHOW:
          return array(
            'PublikationL'       => 'Publikation',
            'Datierung2'         => 'Datierung',
            'Ort'                => 'Ort',
            'OriginaltitelHTML'  => 'Originaltitel',
            'Material'           => 'Material',
            'Abbildung'          => 'Abbildung',
            //'Andere Publikation' => 'Andere Publikationen',
            //'Link1FM'            => 'Link',
            //'ErwDatHTML'         => 'Erwähnte Daten',
            'ddbVol'              => 'Texte der DDBDP',
            /*'DAHTwww' => 'DAHT',
            'LDABwww' => 'LDAB',
            'DFGwww' => 'DFG',
            'BLWWW' => 'Einträge nach BL-Konkordanz',
            'UebersWWW' => 'Übers',
            'Bemerkungen'        => 'Bemerkungen',
            'InhaltHTML'             => 'Inhalt',
            'TM_Nr.HTML'             => 'TM_Nr.'*/
          );
        case self::TYPE_LIST:
        default:
          return array(
            'PublikationL'  => 'Publikation',
            'Datierung2'    => 'Datierung',
            'Ort'           => 'Ort',
            'OriginaltitelHTML' => 'Originaltitel'
          );
      }
    }
    
    
}
