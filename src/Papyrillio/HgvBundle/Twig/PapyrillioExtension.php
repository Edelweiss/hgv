<?php

namespace Papyrillio\HgvBundle\Twig;

use Papyrillio\HgvBundle\Twig\Numbers_Roman;

class PapyrillioExtension extends \Twig_Extension
{
  public function getFilters()
  {
    return array(
      'decode' => new \Twig_Filter_Method($this, 'decode'),
      'roman' => new \Twig_Filter_Method($this, 'roman'),
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

  public function roman($value)
  {
    return Numbers_Roman::toRoman($value);
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