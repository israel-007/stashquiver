<?php
namespace StashQuiver;

use StashQuiver\FormatHandler;
use StashQuiver\RateLimiter;
use StashQuiver\ErrorHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApiRequestHandler
{
    private $client;
    private $apiKey;
    private $useGuzzle;
    private $errorHandler;
    private $rateLimiter;
    private $formatHandler;

    public function __construct($apiKey = null, $useGuzzle = false, $retryLimit = 3, $fallbackResponse = 'Fallback Response', RateLimiter $rateLimiter = null, FormatHandler $formatHandler = null)
    {
        $this->apiKey = $apiKey;
        $this->useGuzzle = $useGuzzle;

        if ($useGuzzle && class_exists(Client::class)) {
            $this->client = new Client();
        }

        $logger = new Logger('StashQuiver');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/Stash_Quiver.log', Logger::ERROR));

        $this->errorHandler = new ErrorHandler($logger, $retryLimit, $fallbackResponse);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter(60, 60);
        $this->formatHandler = $formatHandler ?? new FormatHandler();
    }

    /**
     * Makes a single API request and parses the response.
     * 
     * @param string $url API endpoint.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param array $params Query parameters.
     * @param array $headers Request headers.
     * @param mixed $body Request body data.
     * @param string|null $expectedFormat Optional expected format ('json', 'xml', 'html').
     * @return mixed Parsed response data or fallback response.
     * @throws \Exception if request fails after retries or rate limit exceeded.
     */
    public function makeRequest($url, $method = 'GET', $params = [], $headers = [], $body = null, $expectedFormat = null)
    {
        if (!$this->rateLimiter->allowRequest()) {
            throw new \Exception("Rate limit exceeded. Please wait before making more requests.");
        }

        $urlWithQuery = $url . '?' . http_build_query($params);

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $callback = function () use ($urlWithQuery, $method, $headers, $body, $expectedFormat) {
            if ($this->useGuzzle && $this->client) {
                $response = $this->makeGuzzleRequest($urlWithQuery, $method, $headers, $body);
            } elseif (function_exists('curl_init')) {
                $response = $this->makeCurlRequest($urlWithQuery, $method, $headers, $body);
            } else {
                $response = $this->makeFileGetContentsRequest($urlWithQuery, $method, $headers, $body);
            }

            // Validate and parse response
            $format = $expectedFormat ?? $this->formatHandler->detectFormat($response);
            if ($format && $this->formatHandler->validate($response, $format)) {
                return $this->formatHandler->parse($response, $format);
            }

            return $response; // Return raw response if parsing fails
        };

        return $this->errorHandler->retry($callback);
    }

    /**
     * Makes a batch of API requests with retry logic.
     * 
     * @param array $requests Array of request configurations.
     * @return array Array of responses from each API request.
     */
    public function makeBatchRequest(array $requests)
    {
        $responses = [];

        foreach ($requests as $request) {
            $callback = function () use ($request) {
                return $this->makeRequest(
                    $request['url'],
                    $request['method'] ?? 'GET',
                    $request['params'] ?? [],
                    $request['headers'] ?? [],
                    $request['body'] ?? null
                );
            };

            $responses[] = $this->errorHandler->retry($callback);
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
