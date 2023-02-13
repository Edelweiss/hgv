<?php

namespace App\Service;

class MatomoUrl extends Matomo {
  const MODULE_WIDGET = 'Widgetize';
  const MODULE_API = 'API';
  const FORMAT_JSON = 'JSON';

  public function visitors($module = self::MODULE_API){
    return $this->getApi('VisitFrequency.get', 'range', '2023-01-01,2023-02-15')
  }

  public function getApi($method, $period, $date){
    return $this->url . '?module=' . self::MODULE_API . '&format=' . self::FORMAT_JSON . '&method=' . $method . '&period=' . $period . '&date=' . $date . '&idSite=' . $this->site . '&token_auth=' . $this->token;
  }

  public function cloud($module = self::MODULE_WIDGET){
    return $this->url . '?module=' . $module . '&action=iframe&disableLink=1&widget=1&moduleToWidgetize=UserCountry&actionToWidgetize=getCountry&period=range&date=2023-01-01,2023-02-15&viewDataTable=cloud&filter_limit=42&idSite=' . $this->site . '&token_auth=' . $this->token;
  }
}

?>