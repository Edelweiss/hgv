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
    $translations = array();
    $original = array('in: ', '&amp;', '&quot;', '&lt;', '&gt;');
    $mask = array('#INCOLONSPACE#', '#QUOTATIONMARK#', '#LESSTHAN#', '#GREATERTHAN#');
    $canonical = array('in: ', ' & ', '"', '<', '>');
    $uebersetzungen = str_replace($original, $mask, $input);

    if(preg_match_all('/(([^: ]+): )([^:]+([ \.$\d]|$))/', $uebersetzungen, $matches)){
      if(count($matches[0])){
        foreach ($matches[2] as $index => $language) {
          $translations[$language] = array();
          foreach(explode(';', $matches[3][$index]) as $translation){

            $translations[$language][] = str_replace($mask, $canonical, $translation);
          }
        }
      }
    }

    return $translations;
  }

  public function getName()
  {
    return 'papyrillio_extension';
  }
}