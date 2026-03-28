<?php

namespace App\Service;

use App\Dto\Publication;

/**
 * Service that queries the BaseX HGV XML database for publication data.
 *
 * Provides methods to browse publications by (series, volume, number) triple
 * derived from tei:bibl[@type='publication'][@subtype='principal'].
 */
class PublicationService
{
    private BaseXClient $client;

    public function __construct(BaseXClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get all distinct (series, volume) tuples, ordered by series then volume.
     *
     * @return Publication[]
     */
    public function getVolumes(): array
    {
        $xquery = <<<'XQ'
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";
array {
  for $pub in db:get('hgv')//tei:bibl[@type='publication'][@subtype='principal']
  let $abbr := string($pub/tei:title[@type='abbreviated'])
  let $vol  := string($pub/tei:biblScope[@type='volume'])
  where $abbr != ''
  group by $abbr, $vol
  order by lower-case($abbr) ascending,
           (if ($vol castable as xs:integer) then xs:integer($vol) else 9999) ascending,
           $vol ascending
  return map { "series": $abbr, "volume": $vol }
}
XQ;

        try {
            $result = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return [];
        }

        $publications = [];
        foreach ($result as $row) {
            $publications[] = new Publication(
                (string)($row['series'] ?? ''),
                (string)($row['volume'] ?? '')
            );
        }
        return $publications;
    }

    /**
     * Get all distinct publication numbers for a given (series, volume) pair.
     *
     * @return Publication[]
     */
    public function getNumbers(string $series, string $volume): array
    {
        $safeSeries = $this->escXQ($series);
        $safeVolume = $this->escXQ($volume);

        $xquery = <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";
array {
  for \$pub in db:get('hgv')//tei:bibl[@type='publication'][@subtype='principal']
  let \$abbr := string(\$pub/tei:title[@type='abbreviated'])
  let \$vol  := string(\$pub/tei:biblScope[@type='volume'])
  let \$nr   := string(\$pub/tei:biblScope[@type='numbers'])
  where \$abbr = '$safeSeries' and \$vol = '$safeVolume'
  group by \$nr
  order by (if (\$nr castable as xs:integer) then xs:integer(\$nr) else 9999) ascending,
           \$nr ascending
  return map { "number": \$nr }
}
XQ;

        try {
            $result = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return [];
        }

        $publications = [];
        foreach ($result as $row) {
            $publications[] = new Publication(
                $series,
                $volume,
                (string)($row['number'] ?? '')
            );
        }
        return $publications;
    }

    /**
     * Find HGV record IDs matching a specific (series, volume, number) triple.
     *
     * @return string[]
     */
    public function findRecordIds(string $series, string $volume, string $number): array
    {
        $safeSeries = $this->escXQ($series);
        $safeVolume = $this->escXQ($volume);
        $safeNumber = $this->escXQ($number);

        $xquery = <<<XQ
declare namespace tei = "http://www.tei-c.org/ns/1.0";
declare option output:method "json";
array {
  for \$doc in db:get('hgv')/tei:TEI
  let \$pub := (\$doc//tei:bibl[@type='publication'][@subtype='principal'])[1]
  where string(\$pub/tei:title[@type='abbreviated']) = '$safeSeries'
    and string(\$pub/tei:biblScope[@type='volume'])  = '$safeVolume'
    and string(\$pub/tei:biblScope[@type='numbers']) = '$safeNumber'
  order by string(\$doc//tei:idno[@type='filename']) ascending
  return string(\$doc//tei:idno[@type='filename'])
}
XQ;

        try {
            $result = $this->client->xqueryJson($xquery);
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($result)) {
            return [];
        }

        return array_map('strval', $result);
    }

    private function escXQ(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
