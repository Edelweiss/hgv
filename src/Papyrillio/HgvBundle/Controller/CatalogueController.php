<?php

namespace Papyrillio\HgvBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FileMaker_Error;


class CatalogueController extends Controller
{
    
    public function indexAction()
    {
        return $this->render('PapyrillioHgvBundle:Catalogue:index.html.twig');
    }
    
    protected function getParameter($key){
      $get  = $this->getRequest()->query->get($key);
      $post = $this->getRequest()->request->get($key);
      
      if($post && (is_array($post) || strlen(trim($post)))){
        return $post;
      }
  
      return $get;
    }
    
    protected function getParameterSearch(){
      $search = $this->getParameter('search');
      
      // collect valid filters and straighten data types
      $criteria = array();
      foreach($search['criteria'] as $key => $criterion){

        if(!empty($criterion['value'])){
          $criteria[$key] = array(
            'value' => trim($criterion['value']),
            'operator' => $criterion['operator']
          );
        }
      }
      $search['criteria'] = $criteria;

      return $search;
    }

    protected function getParameterSort(){
      return array(
        1 => array('field' => 'J', 'direction' => FILEMAKER_SORT_ASCEND),
        2 => array('field' => 'M', 'direction' => FILEMAKER_SORT_ASCEND),
        3 => array('field' => 'T', 'direction' => FILEMAKER_SORT_ASCEND),
        4 => array('field' => 'ChronGlobal', 'direction' => FILEMAKER_SORT_ASCEND)
      );
    }

    public function listAction()
    {
        $fm = $this->get('papyrillio_hgv.file_maker_hgv');
        
        $filterList = $this->getParameterSearch();
        $sortList = $this->getParameterSort();
        
        var_dump($filterList);

        $result = $fm->search($filterList, $sortList);
        
        if($fm->isError($result)){
          return $this->render('PapyrillioHgvBundle:Catalogue:error.html.twig', array('message' => 'FileMaker Error #' . $result->code . ': ' . $result->getMessage()));
        } else {
          $from = $filterList['skip'] + 1;
          $to = min($result->getFetchCount(), $filterList['max']);
          return $this->render('PapyrillioHgvBundle:Catalogue:list.html.twig', array(
            'search'             => $filterList,
            'fieldList'          => self::getFieldList(),
            'operatorSymbolList' => self::getOperatorSymbolList(),
            'operatorList'       => self::getOperatorList(), 
            'from'               => $from, 
            'to'                 => $to, 
            'foundSetCount'      => $result->getFoundSetCount(), 
            'tableRecordCount'   => $result->getTableRecordCount(), 
            'result'             => $result));
        }
    }
    
    public function showAction($name)
    {
        return $this->render('PapyrillioHgvBundle:Catalogue:show.html.twig');
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
          'TM_Nr.'             => 'TM_Nr.',
          'J'                  => 'J',
          'M'                  => 'M',
          'T'                  => 'T',
          'J2'                 => 'J2',
          'Jh'                 => 'Jh',
          'Jh2'                => 'Jh2',
          'Ort'                => 'Ort',
          'Originaltitel'      => 'Originaltitel',
          'Material'           => 'Material',
          'Abbildung'          => 'Abbildung',
          'Andere Publikation' => 'Andere Publikation',
          'Bemerkungen'        => 'Bemerkungen',
          'Inhalt'             => 'Inhalt',
          'Link1FM'            => 'Link1FM',
          'ChronMinimum'       => 'ChronMinimum',
          'ChronMaximum'       => 'ChronMaximum',
          'ChronGlobal'        => 'ChronGlobal',
          'Uebersetzungen'     => 'Uebersetzungen'
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
    
    protected static function getFieldList(){
      return array(
        'PublikationL'  => 'Publikation',
        'Datierung2'    => 'Datierung',
        'Ort'           => 'Ort',
        'Originaltitel' => 'Originaltitel'
      );
    }
    
    
}
