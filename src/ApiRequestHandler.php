<?php
namespace StashQuiver;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ApiRequestHandler
{
    private $client;
    private $apiKey;
    private $useGuzzle;

    public function __construct($apiKey = null, $useGuzzle = false)
    {
        $this->apiKey = $apiKey;
        $this->useGuzzle = $useGuzzle;

        if ($useGuzzle && class_exists(Client::class)) {
            $this->client = new Client();
        }
    }

    /**
     * Sets an API key for requests that require authorization.
     * 
     * @param string $apiKey API key to be added to requests.
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Makes a single API request.
     * 
     * @param string $url API endpoint.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param array $params Query parameters.
     * @param array $headers Request headers.
     * @param mixed $body Request body data.
     * @return mixed API response data.
     * @throws \Exception if request fails.
     */
    public function makeRequest($url, $method = 'GET', $params = [], $headers = [], $body = null)
    {
        $urlWithQuery = $url . '?' . http_build_query($params);

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            if ($this->useGuzzle && $this->client) {
                return $this->makeGuzzleRequest($urlWithQuery, $method, $headers, $body);
            } elseif (function_exists('curl_init')) {
                return $this->makeCurlRequest($urlWithQuery, $method, $headers, $body);
            } else {
                return $this->makeFileGetContentsRequest($urlWithQuery, $method, $headers, $body);
            }
        } catch (\Exception $e) {
            throw new \Exception("API request failed: " . $e->getMessage());
        }
    }

    /**
     * Makes a batch of API requests.
     * 
     * @param array $requests Array of request configurations.
     * @return array Array of responses from each API request.
     */
    public function makeBatchRequest(array $requests)
    {
        $responses = [];

        foreach ($requests as $request) {
            $responses[] = $this->makeRequest(
                $request['url'],
                $request['method'] ?? 'GET',
                $request['params'] ?? [],
                $request['headers'] ?? [],
                $request['body'] ?? null
            );
        }

        return $responses;
    }

    /**
     * Makes a Guzzle HTTP request.
     */
    private function makeGuzzleRequest($url, $method, $headers, $body)
    {
        $options = [
            'headers' => $headers,
            'body' => $body
        ];

        $response = $this->client->request($method, $url, $options);
        return (string) $response->getBody();
    }

    /**
     * Makes a CURL HTTP request.
     */
    private function makeCurlRequest($url, $method, $headers, $body)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Makes a request using file_get_contents.
     */
    private function makeFileGetContentsRequest($url, $method, $headers, $body)
    {
        $options = [
            'http' => [
                'header' => $this->formatHeaders($headers),
                'method' => $method,
                'content' => $body
            ]
        ];

        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }

    /**
     * Formats headers for use in CURL and file_get_contents.
     * 
     * @param array $headers Associative array of headers.
     * @return array Formatted headers for request.
     */
    private function formatHeaders($headers)
    {
        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "$key: $value";
        }
        return $formattedHeaders;
    }
}
