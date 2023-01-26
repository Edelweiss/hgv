<?php

namespace App\Service;

class Matomo {
  private token = '';

  /*protected function configure($path, $file, $direction = null){
    $this->loadImage($path, $file);
    $this->direction = $direction == ImagePeer::DIRECTION_CLOCKWISE ? $direction : ImagePeer::DIRECTION_COUNTERCLOCKWISE; 
  }*/

  public function htmlCloud(){
    //$this->configure($path, $file, $direction);
    // https://papy.zaw.uni-heidelberg.de/tomo/index.php?module=Widgetize&action=iframe&disableLink=1&widget=1&moduleToWidgetize=UserCountry&actionToWidgetize=getCountry&idSite=1&period=day&date=yesterday&viewDataTable=graphPie&token_auth=d3657e451381a2c526d74b0532e05c25
    return '<div>Cloud</div>';
  }
}

?>