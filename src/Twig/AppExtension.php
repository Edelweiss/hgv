<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/*use Twig\Numbers_Roman;
*/
class AppExtension extends AbstractExtension
{
  public function getFilters()
  {
    return [
      new TwigFilter('decode', [$this, 'decode']),
      new TwigFilter('roman', [$this, 'roman'])
    ];
  }

  function getFunctions()
  {
    return [
      new TwigFunction('processTranslations', [$this, 'processTranslations'])
    ];
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