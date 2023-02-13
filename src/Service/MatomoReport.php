<?php

namespace App\Service;

class MatomoReport extends MatomoUrl {
  protected function getHtml($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
  }

  public function getVisitors(){
    return $this->getApiUrl('VisitFrequency.get', 'range', '2023-01-01,2023-02-15');
    return $this->getHtml($this->getApiUrl('VisitFrequency.get', 'range', '2023-01-01,2023-02-15'));
  }

  public function htmlCloud(){
    return $this->getHtml($this->cloud());
  }
}

?>