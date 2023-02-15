<?php

namespace App\Service;

class MatomoReport extends MatomoUrl {
  protected $apiResponses = [];

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
  //https://papy.zaw.uni-heidelberg.de/tomo/index.php?module=API&method=UserCountry.getCountry&idSite=1&period=day&date=yesterday&format=JSON&token_auth=8b08c00be37339ffa8ad668e82390398&force_api_session=1

  public function getCountries(){
    $countries = [];
    if($a = json_decode($this->getResponse($this->getApiUrl('UserCountry.getCountry', 'range', '2023-01-15,2023-02-13')), true)){
      foreach($a as $country){
        $countries[$country['label']] = $country['nb_visits'];
      }
      return $countries;
    }
    return 0;
  }

  public function getVisitors(){
    if($v = json_decode($this->getResponse($this->getApiUrl('VisitFrequency.get', 'range', '2023-01-15,2023-02-13')), true)){
      return $v['nb_actions_new'];
    }
    return 0;
  }

  public function getCloud(){
    return $this->getResponse($this->getCloudWidgetUrl());
  }
}

?>