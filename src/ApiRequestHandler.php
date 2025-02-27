<?php
namespace StashQuiver;

use StashQuiver\RateLimiter;
use StashQuiver\CacheManager;
use StashQuiver\ErrorHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
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
    private $timeout;
    private $debug;
    private $defaultHeaders = [
        'User-Agent' => 'StashQuiver/1.0 (PHP API Client)',
        'Accept' => 'application/json',
    ];

    public function __construct(
        $apiKey = null,
        $useGuzzle = false,
        $retryLimit = 3,
        $fallbackResponse = 'Fallback Response',
        RateLimiter $rateLimiter = null,
        CacheManager $cacheManager = null,
        $timeout = 10,
        $debug = false
    ) {
        $this->apiKey = $apiKey;
        $this->useGuzzle = $useGuzzle;
        $this->timeout = $timeout;
        $this->debug = $debug;

        if ($useGuzzle && class_exists(Client::class)) {
            $this->client = new Client(['timeout' => $timeout]);
        }

        $logger = new Logger('APIStash');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/../../logs/api_stash.log', Logger::DEBUG));

        $this->errorHandler = new ErrorHandler($logger, $retryLimit, $fallbackResponse);
        $this->rateLimiter = $rateLimiter ?? new RateLimiter(60, 60);
        $this->cacheManager = $cacheManager ?? new CacheManager();
    }

    /**
     * Makes a single API request with retry, rate limiting, and caching.
     */
    public function makeRequest($url, $method = 'GET', $params = [], $headers = [], $body = null, $cache = true)
    {
        if (!$this->rateLimiter->allowRequest()) {
            throw new \Exception("Rate limit exceeded. Please wait before making more requests.");
        }

        $headers = array_merge($this->defaultHeaders, $headers);
        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $cacheKey = $this->generateCacheKey($url, $method, $params, $headers, $body);
        if ($cache && ($cachedResponse = $this->cacheManager->retrieve($cacheKey)) !== null) {
            return $cachedResponse;
        }

        $attempts = 0;
        while ($attempts < 3) {
            try {
                $urlWithQuery = $url . '?' . http_build_query($params);
                $response = $this->useGuzzle ? $this->makeGuzzleRequest($urlWithQuery, $method, $headers, $body)
                    : $this->makeCurlRequest($urlWithQuery, $method, $headers, $body);

                if ($cache) {
                    $this->cacheManager->store($cacheKey, $response, 3600);
                }

                return $response;
            } catch (\Exception $e) {
                $attempts++;
                if ($this->debug) {
                    error_log("Request failed (attempt $attempts): " . $e->getMessage());
                }
                if ($attempts >= 3) {
                    // return $this->errorHandler->getFallbackResponse();
                    return $this->errorHandler->getFallbackResponse();
                }
                sleep(2 ** $attempts);
            }
        }
    }

    /**
     * Makes an asynchronous API request.
     */
    public function makeAsyncRequest($url, $method = 'GET', $params = [], $headers = [], $body = null)
    {
        if (!$this->useGuzzle) {
            throw new \Exception("Async requests require Guzzle. Enable Guzzle in the constructor.");
        }

        $headers = array_merge($this->defaultHeaders, $headers);
        $urlWithQuery = $url . '?' . http_build_query($params);

        return $this->client->requestAsync($method, $urlWithQuery, [
            'headers' => $headers,
            'body' => is_array($body) ? json_encode($body) : $body,
        ]);
    }

    /**
     * Executes multiple async requests in parallel.
     */
    public function makeBatchAsyncRequest(array $requests)
    {
        if (!$this->useGuzzle) {
            throw new \Exception("Async batch requests require Guzzle.");
        }

        $promises = [];
        foreach ($requests as $req) {
            $promises[] = $this->makeAsyncRequest(
                $req['url'],
                $req['method'] ?? 'GET',
                $req['params'] ?? [],
                $req['headers'] ?? [],
                $req['body'] ?? null
            );
        }

        return \GuzzleHttp\Promise\Utils::settle($promises)->wait();
    }

    private function makeGuzzleRequest($url, $method, $headers, $body)
    {
        $response = $this->client->request($method, $url, [
            'headers' => $headers,
            'body' => is_array($body) ? json_encode($body) : $body,
        ]);
        return (string) $response->getBody();
    }

    private function makeCurlRequest($url, $method, $headers, $body)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function formatHeaders($headers)
    {
        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "$key: $value";
        }
        return $formattedHeaders;
    }

    private function generateCacheKey($url, $method, $params, $headers, $body)
    {
        return md5(serialize([
            'url' => $url,
            'method' => $method,
            'params' => $params,
            'headers' => $headers,
            'body' => $body
        ]));
    }
}
