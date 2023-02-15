<?php

namespace App\Service;

class MatomoUrl extends Matomo {
  const MODULE_WIDGET = 'Widgetize&action=iframe&disableLink=1&widget=1';
  const MODULE_API = 'API';
  const FORMAT_JSON = 'JSON';

  private function getUrl($parameters){
    return $this->url . $parameters . '&language=de&idSite=' . $this->site . '&token_auth=' . $this->token;
  }

  public function getApiUrl($method, $period, $date){
    return $this->getUrl('?module=' . self::MODULE_API . '&format=' . self::FORMAT_JSON . '&method=' . $method . '&period=' . $period . '&date=' . $date);
  }

  public function getWidgetUrl($widget, $action, $period, $date, $extra = []){
    $extraParameters = '';
    foreach($extra as $key => $value){
      $extraParameters .= '&' . $key . '=' . $value;
    }
    return $this->url . '?module=' . self::MODULE_WIDGET . '&moduleToWidgetize=' . $widget . '&actionToWidgetize=' . $action . '&period=' . $period . '&date=' . $date . $extraParameters . '&idSite=' . $this->site . '&token_auth=' . $this->token;
  }

  // API

  public function getVisitorApiUrl(){
    return $this->getApiUrl('VisitFrequency.get', 'range', '2023-01-16,2023-02-13');
  }

  // WIDGET

  public function getCloudWidgetUrl(){
    return $this->getWidgetUrl('UserCountry', 'getCountry', 'range', '2023-01-16,2023-02-13', ['viewDataTable' => 'cloud', 'filter_limit' => '42']);
  }
}

?>