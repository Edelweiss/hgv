<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for BaseX REST API.
 *
 * Provides query execution and document retrieval against BaseX databases
 * holding HGV_meta_EpiDoc and DDB_EpiDoc_XML data.
 */
class BaseXClient
{
    private HttpClientInterface $httpClient;
    private string $baseUrl;
    private string $user;
    private string $password;

    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        string $user,
        string $password
    ) {
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Execute an XQuery and return the result as a string.
     */
    public function xquery(string $query): string
    {
        $response = $this->httpClient->request('POST', $this->baseUrl, [
            'auth_basic' => [$this->user, $this->password],
            'headers' => [
                'Content-Type' => 'application/xquery',
            ],
            'body' => $query,
        ]);

        return $response->getContent();
    }

    /**
     * Execute an XQuery that returns JSON (via XQuery serialization).
     *
     * The XQuery should contain:
     *   declare option output:method 'json';
     *   declare option output:json 'format=basic';
     */
    public function xqueryJson(string $query): array
    {
        $result = $this->xquery($query);

        return json_decode($result, true) ?? [];
    }

    /**
     * Retrieve a single document by database name and path.
     *
     * @param string $database  e.g. "hgv" or "ddb"
     * @param string $path      e.g. "HGV1/1.xml"
     */
    public function getDocument(string $database, string $path): string
    {
        $url = $this->baseUrl . '/' . urlencode($database) . '/' . $path;

        $response = $this->httpClient->request('GET', $url, [
            'auth_basic' => [$this->user, $this->password],
        ]);

        return $response->getContent();
    }

    /**
     * Execute an XQuery that returns XML and parse it into a SimpleXMLElement.
     */
    public function xqueryXml(string $query): ?\SimpleXMLElement
    {
        $result = $this->xquery($query);

        if (empty($result)) {
            return null;
        }

        $xml = @simplexml_load_string($result);
        return $xml ?: null;
    }
}
