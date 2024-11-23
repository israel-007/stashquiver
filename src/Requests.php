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
    public function __construct()
    {
        parent::__construct();
        $this->rateLimiter([5, 60]);       // Default rate limit
        $this->cacheManager(true);         // Default to using CacheManager
    }

    /**
     * Sets the API endpoint URL.
     * 
     * @param string $url The API endpoint URL.
     * @return self
     */
    public function url($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Sets the HTTP method (GET, POST, etc.).
     * 
     * @param string $method The HTTP method.
     * @return self
     */
    public function method($method = 'GET')
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Sets the query parameters.
     * 
     * @param array $params Associative array of query parameters.
     * @return self
     */
    public function params(array $params = [])
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Sets the request headers.
     * 
     * @param array $headers Associative array of headers.
     * @return self
     */
    public function headers(array $headers = [])
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Sets the request body data.
     * 
     * @param mixed $body The request body data.
     * @return self
     */
    public function body($body = null)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Sets the expected response format (json, xml, html).
     * 
     * @param string $format The expected response format.
     * @return self
     */
    public function format(string $format = 'json')
    {
        $this->expectedFormat = strtolower($format);
        return $this;
    }

    /**
     * Enables or disables caching for this request.
     * 
     * @param bool $cache True to enable caching, false to disable.
     * @return self
     */
    public function useCache($cache = true)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Sets the API key.
     * 
     * @param string $apiKey API key for authentication.
     * @return self
     */
    public function apiKey($apiKey = null)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Sets the retry limit for the request.
     * 
     * @param int $retryLimit Number of retries for failed requests.
     * @return self
     */
    public function retryLimit(int $retryLimit = 3)
    {
        $this->retryLimit = $retryLimit;
        return $this;
    }

    /**
     * Sets the fallback response if retries fail.
     * 
     * @param mixed $fallbackResponse The fallback response.
     * @return self
     */
    public function fallbackResponse(string $fallbackResponse = 'Error: Retry limit reached')
    {
        $this->fallbackResponse = $fallbackResponse;
        return $this;
    }

    /**
     * Configures the rate limiter with the given settings.
     * 
     * @param array $settings Rate limit settings [maxRequests, periodInSeconds].
     * @return self
     */
    public function rateLimiter(array $settings = [5, 60])
    {
        [$maxRequests, $periodInSeconds] = $settings;
        $this->rateLimiter = new RateLimiter($maxRequests, $periodInSeconds);
        return $this;
    }

    /**
     * Enables or disables the CacheManager.
     * 
     * @param bool $useCacheManager True to enable CacheManager, false to disable.
     * @return self
     */
    private function cacheManager($useCacheManager = [])
    {
        $this->cacheManager = $useCacheManager ? new CacheManager() : null;
        return $this;
    }

    /**
     * Executes the API request with the specified configuration.
     * 
     * @return mixed The response from the API.
     */
    public function send()
    {
        // Initialize ErrorHandler with logger and configurations
        $logger = new Logger('StashQuiver');
        $logger->pushHandler(new StreamHandler(__DIR__ . 'logs/stash_quiver.log', Logger::ERROR));

        return $this->makeRequest(
            $this->url,
            $this->method,
            $this->params,
            $this->headers,
            $this->body,
            $this->cache
        );
    }
}
