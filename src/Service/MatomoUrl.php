<?php

namespace App\Service;

class MatomoUrl extends Matomo {
  const MODULE_WIDGET = 'Widgetize';
  const MODULE_API = 'API';

  public function cloud($module = self::MODULE_WIDGET){
    $return = $this->url . '?module=' . $module . '&action=iframe&disableLink=1&widget=1&moduleToWidgetize=UserCountry&actionToWidgetize=getCountry&idSite=' . $this->site . '&period=day&date=yesterday&viewDataTable=graphPie&token_auth=' . $this->token;
  }
}

?>