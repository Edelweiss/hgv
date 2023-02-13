<?php

namespace App\Service;

class MatomoUrl extends Matomo {
  const MODULE_WIDGET = 'Widgetize&action=iframe&disableLink=1&widget=1';
  const MODULE_API = 'API';
  const FORMAT_JSON = 'JSON';
  
  private function getUrl($parameters){
    return $this->url . $parameters . '&idSite=' . $this->site . '&token_auth=' . $this->token;
  }

  public function getApiUrl($method, $period, $date){
    return $this->getUrl('?module=' . self::MODULE_API . '&format=' . self::FORMAT_JSON . '&method=' . $method . '&period=' . $period . '&date=' . $date);
  }

  public function getWidgetUrl($widget, $action, $period, $date, $extra = ''){
    return $this->url . '?module=' . self::MODULE_WIDGET . '&moduleToWidgetize' . $widget . '&actionToWidgetize' . $action . '&period=' . $period . '&date=' . $date . $extra . '&idSite=' . $this->site . '&token_auth=' . $this->token;
  }

  // API

  public function getVisitorApiUrl(){
    return $this->getApiUrl('VisitFrequency.get', 'range', '2023-01-01,2023-02-15');
  }

  // WIDGET

  public function getCloudWidgetUrl(){
    return this->getWidgetUrl('UserCountry', 'getCountry', 'range', '2023-01-01,2023-02-15', '&viewDataTable=cloud&filter_limit=42');
    //return $this->url . '?module=' . $module . '&action=iframe&disableLink=1&widget=1&moduleToWidgetize=UserCountry&actionToWidgetize=getCountry&period=range&date=2023-01-01,2023-02-15&viewDataTable=cloud&filter_limit=42&idSite=' . $this->site . '&token_auth=' . $this->token;
  }
}

?>