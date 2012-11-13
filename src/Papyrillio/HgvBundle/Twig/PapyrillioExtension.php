<?php

namespace Papyrillio\HgvBundle\Twig;

class PapyrillioExtension extends \Twig_Extension
{
  public function getFilters()
  {
    return array(
      'decode' => new \Twig_Filter_Method($this, 'decode'),
    );
  }
  
  function getFunctions()
   {
    return array(
        'processTranslations' => new \Twig_Function_Method($this, 'processTranslations')
    );
   }

  public function decode($value)
  {
    return html_entity_decode($value);
  }

  public function processTranslations($input)
  {
    if(preg_match_all('/(([^: ]+): )([^:]+([ \.$\d]|$))/', $input, $matches)){
      return '<i>italic</i>';
    } else {
      return $input;
    }
  }

  public function getName()
  {
    return 'papyrillio_extension';
  }
}