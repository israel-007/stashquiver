<?php

namespace StashQuiver;

use StashQuiver\RateLimiter;
use StashQuiver\CacheManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Requests extends ApiRequestHandler
{
    private $url;
    private $method = 'GET';
    private $params = [];
    private $headers = [];
    private $body = null;
    private $expectedFormat = 'json';
    private $cache = true;
    private $retryLimit = 3;
    private $fallbackResponse = 'Error: Retry limit reached';

    /**
     * Initializes optional dependencies with default values.
     */
    public function __construct($apiKey = null, $useGuzzle = false)
    {
        parent::__construct($apiKey, $useGuzzle);
        $this->rateLimiter([5, 60]);
        $this->cacheManager(true);
    }

    public function url($url)
    {
        $this->url = $url;
        return $this;
    }
    public function method($method = 'GET')
    {
        $this->method = strtoupper($method);
        return $this;
    }
    public function params(array $params = [])
    {
        $this->params = $params;
        return $this;
    }
    public function headers(array $headers = [])
    {
        $this->headers = $headers;
        return $this;
    }
    public function body($body = null)
    {
        $this->body = $body;
        return $this;
    }
    public function useCache($cache = true)
    {
        $this->cache = $cache;
        return $this;
    }
    public function apiKey($apiKey = null)
    {
        $this->apiKey = $apiKey;
        return $this;
    }
    public function retryLimit(int $retryLimit = 3)
    {
        $this->retryLimit = $retryLimit;
        return $this;
    }
    public function fallbackResponse(string $fallbackResponse = 'Error: An error occured while making request.')
    {
        $this->fallbackResponse = $fallbackResponse;
        return $this;
    }
    public function rateLimiter(array $settings = [10, 60])
    {
        [$maxRequests, $periodInSeconds] = $settings;
        $this->rateLimiter = new RateLimiter($maxRequests, $periodInSeconds);
        return $this;
    }

    /**
     * Enables or disables the CacheManager.
     */
    public function cacheManager(bool $useCacheManager = true)
    {
        $this->cacheManager = $useCacheManager ? new CacheManager() : null;
        return $this;
    }

    /**
     * Executes the API request.
     */
    public function send()
    {
        $logger = new Logger('StashQuiver');
        $logger->pushHandler(new StreamHandler(__DIR__ . '/logs/stash_quiver.log', Logger::ERROR));

        return $this->makeRequest(
            $this->url,
            $this->method,
            $this->params,
            $this->headers,
            $this->body,
            $this->cache
        );
    }

    /**
     * Alias for send() to improve readability.
     */
    public function execute()
    {
        return $this->send();
    }
}
