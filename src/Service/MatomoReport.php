<?php

namespace App\Service;

class MatomoReport extends MatomoUrl {
  protected function getResponse($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
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