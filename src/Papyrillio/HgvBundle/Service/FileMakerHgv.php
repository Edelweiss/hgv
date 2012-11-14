<?php

namespace Papyrillio\HgvBundle\Service;

ini_set("error_reporting", E_ALL & ~E_DEPRECATED);

require_once '/usr/lib/php/FileMaker.php';
require_once '/usr/lib/php/FileMaker/Command/Find.php';
require_once '/usr/lib/php/FileMaker/Implementation/Command/FindImpl.php';

use FileMaker;
use FileMaker_Error;
use FileMaker_Command_Find;
use FileMaker_Command_Find_Implementation;

class FindHgv extends FileMaker_Command_Find{
  function __construct($fm, $layout){
      $this->_impl = new FindImplementationHgv($fm, $layout);
  }

  function addFindCriterion($fieldname, $testvalue, $operator = null)
  {
    $this->_impl->addFindCriterion($fieldname, $testvalue, $operator);
  }
}

class FindImplementationHgv extends FileMaker_Command_Find_Implementation{
  function &execute(){
    $commandParams = $this->_getCommandParams();
    $this->_setSortParams($commandParams);
    $this->_setRangeParams($commandParams);
    $this->_setRelatedSetsFilters($commandParams);   

    // search type (find or findall)

    if(count($this->_findCriteria) || $this->_recordId){
      $commandParams['-find'] = true;
    } else {
      $commandParams['-findall'] = true;
    }

    // retrieve single record via record id?

    if($this->_recordId){
      $commandParams['-recid'] = $this->_recordId;
    }

    // set logical operator

    if($this->Vf951bdce){
      $commandParams['-lop'] = $this->Vf951bdce;
    }

    // set search criteria

    foreach ($this->_findCriteria as $key => $criterion){
      $commandParams[$key] = $criterion['value'];
      $commandParams[$key.'.op'] = $criterion['operator'];
    }

    // var_dump($commandParams);

    // get result

    $result = $this->_fm->_execute($commandParams);

    // return (error or result)

    if (FileMaker::isError($result)) {
     return $result;
    }

    return $this->_getResult($result);
  }

  function addFindCriterion($key, $value, $operator = null){
    $this->_findCriteria[$key] = array('value' => $value, 'operator' => $operator ? $operator : 'eq');
  }

}

class FileMakerHgv extends FileMaker{
  const LAYOUT = 'Web';

  public function __construct($database = NULL, $hostspec = NULL, $username = NULL, $password = NULL){
    parent::__construct($database, $hostspec, $username, $password);
  }

  public function search($filterList = array(), $sortList = array()){
    $skip     = $filterList['skip'];
    $max      = $filterList['max'];
    $criteria = $filterList['criteria'];
    $operator = $filterList['operator'];

    $findCommand = $this->newFindCommand(self::LAYOUT);
    $findCommand->setRange($skip, $max);

    if(in_array($operator, array(FILEMAKER_FIND_AND, FILEMAKER_FIND_OR))){
      $findCommand->setLogicalOperator($operator);
    }

    foreach($criteria as $key => $filter){
      if(!empty($filter['value'])){
        $findCommand->addFindCriterion($key, $filter['value'], $filter['operator']);
      }
    }

    foreach($sortList as $index => $sort){
      $findCommand->addSortRule($sort['key'], $index, $sort['direction']);
    }

    return $findCommand->execute();
  }
  
  public function pick(){
    $findCommand = $this->newFindAllCommand(self::LAYOUT);
    $findCommand->setRange(0, 1);
    return $findCommand->execute();
  }
  
  // OVERLORDED STUFF

  public function &newFindCommand($layout) { 
    $findCommand = new FindHgv($this->_impl, $layout);
    return $findCommand;
  }

}

?>