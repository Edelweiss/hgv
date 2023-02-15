<?php

namespace App\Service;

class MatomoReport extends MatomoUrl {
  protected $apiResponses = [];
  protected $date = '2023-01-15,2023-02-13';

  protected function getResponse($url){
    if(strpos($url, self::MODULE_API !== false) && isset($this->apiResponses[$url])){
      return $this->apiResponses[$url];
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    if(strpos($url, self::MODULE_API !== false)){
      $this->apiResponses[$url] = $response;
    }
    return $response;
  }

  protected function selectInfos($a, $keyParamter, $valueParameter){
    $infos = [];
    if(is_array($a)){
      foreach($a as $item){
        $infos[$item[$keyParamter]] = $item[$valueParameter];
      }
    }
    return $infos;
  }
  
  public function getSearchKeywords(){
    return $this->selectInfos(
      json_decode($this->getResponse($this->getApiUrl('Actions.getSiteSearchKeywords', 'range', $this->date)), true),
      'label',
      'nb_hits'
    );
  }

  public function getCountries(){
    return $this->selectInfos(
      json_decode($this->getResponse($this->getApiUrl('UserCountry.getCountry', 'range', $this->date)), true),
      'label',
      'nb_visits'
    );
  }

  public function getVisitors(){
    if($v = json_decode($this->getResponse($this->getApiUrl('VisitFrequency.get', 'range', $this->date)), true)){
      return $v['nb_actions_new'];
    }
    return 0;
  }

  public function getCloud(){
    return $this->getResponse($this->getCloudWidgetUrl());
  }
}

?>