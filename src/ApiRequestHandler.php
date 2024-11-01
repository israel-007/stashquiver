<?php
namespace StashQuiver;

use StashQuiver\CacheManager;
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
    protected $apiKey;
    private $useGuzzle;
    private $errorHandler;
    protected $rateLimiter;
    protected $cacheManager;


    // private $client;
    // protected $apiKey;
    // private $useGuzzle;
    // protected $errorHandler;
    // protected $rateLimiter;
    protected $formatHandler;
    // protected $cacheManager;

    public function __construct(
        $apiKey = null,
        $useGuzzle = false,
        $retryLimit = 3,
        $fallbackResponse = 'Fallback Response',
        RateLimiter $rateLimiter = null,
        CacheManager $cacheManager = null
    ) {
        $this->apiKey = $apiKey;
        $this->useGuzzle = $useGuzzle;

        if ($useGuzzle && class_exists(Client::class)) {
            $this->client = new Client();
        }

        // Set up default components if not provided
        $logger = new Logger('APIStash');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/../../logs/api_stash.log', Logger::ERROR));

        $this->errorHandler = new ErrorHandler($logger, $retryLimit, $fallbackResponse);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter(60, 60);
        $this->cacheManager = $cacheManager ?? new CacheManager();
    }

    /**
     * Makes a single API request with retry, rate limit, and caching.
     * 
     * @param string $url API endpoint.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param array $params Query parameters.
     * @param array $headers Request headers.
     * @param mixed $body Request body data.
     * @param bool $cache Whether to cache the response.
     * @return mixed Raw response data or fallback response.
     * @throws \Exception if request fails after retries or rate limit exceeded.
     */
    public function makeRequest(
        $url,
        $method = 'GET',
        $params = [],
        $headers = [],
        $body = null,
        $cache = true
    ) {
        if (!$this->rateLimiter->allowRequest()) {
            throw new \Exception("Rate limit exceeded. Please wait before making more requests.");
        }

        // Generate unique cache key based on request details
        $cacheKey = $this->generateCacheKey($url, $method, $params, $headers, $body);

        // Check cache for response if caching is enabled
        if ($cache && ($cachedResponse = $this->cacheManager->retrieve($cacheKey)) !== null) {
            return $cachedResponse;
        }

        // Prepare full URL with query parameters
        $urlWithQuery = $url . '?' . http_build_query($params);

        // Add API key to headers if provided
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        // Define the request callback
        $callback = function () use ($urlWithQuery, $method, $headers, $body, $cacheKey, $cache) {
            // Make the HTTP request using the appropriate method
            $response = $this->useGuzzle && $this->client ?
                $this->makeGuzzleRequest($urlWithQuery, $method, $headers, $body) :
                (function_exists('curl_init') ? $this->makeCurlRequest($urlWithQuery, $method, $headers, $body) :
                    $this->makeFileGetContentsRequest($urlWithQuery, $method, $headers, $body));

            // Cache response if caching is enabled
            if ($cache) {
                $this->cacheManager->store($cacheKey, $response, 3600); // Cache for 1 hour
            }

            return $response; // Return raw response
        };

        // Use ErrorHandler to retry the request if it fails
        return $this->errorHandler->retry($callback);
    }

    /**
     * Generates a unique cache key based on the API request parameters.
     * 
     * @param string $url API endpoint.
     * @param string $method HTTP method.
     * @param array $params Query parameters.
     * @param array $headers Request headers.
     * @param mixed $body Request body data.
     * @return string The unique cache key.
     */
    private function generateCacheKey($url, $method, $params, $headers, $body)
    {
        $keyString = serialize([
            'url' => $url,
            'method' => $method,
            'params' => $params,
            'headers' => $headers,
            'body' => $body
        ]);
        return md5($keyString);
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
