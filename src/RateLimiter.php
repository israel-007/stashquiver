<?php
namespace StashQuiver;

use SplQueue;

class RateLimiter
{
    private $limit;
    private $window;
    private $requests;
    private $storageFile;

    /**
     * Initializes the RateLimiter with a limit, time window, and optional persistent storage.
     *
     * @param int $limit The maximum number of requests allowed.
     * @param int $window The time window in seconds.
     * @param string|null $storageFile Optional file path for persistent rate limiting.
     */
    public function __construct($limit = 60, $window = 60, $storageFile = __DIR__ . '/rate_limits/rate_limit_data.txt')
    {
        $this->limit = $limit;
        $this->window = $window;
        $this->requests = new SplQueue();
        $this->storageFile = $storageFile;

        if ($storageFile) {
            $this->loadRequests();
        }
    }

    /**
     * Checks if a request is allowed based on the rate limit.
     *
     * @return bool True if the request is allowed; otherwise, it waits or denies the request.
     */
    public function allowRequest()
    {
        $currentTime = time();

        // Remove expired timestamps
        while (!$this->requests->isEmpty() && ($currentTime - $this->requests->bottom()) >= $this->window) {
            $this->requests->dequeue();
        }

        if ($this->requests->count() < $this->limit) {
            $this->requests->enqueue($currentTime);
            $this->saveRequests(); // Persist timestamps
            return true;
        }

        // If rate limit exceeded, wait for the oldest request to expire
        $waitTime = ($this->requests->bottom() + $this->window) - $currentTime;
        if ($waitTime > 0) {
            sleep($waitTime);
        }

        return $this->allowRequest(); // Retry after waiting
    }

    /**
     * Resets the rate limiter manually.
     */
    public function reset()
    {
        $this->requests = new SplQueue();
        if ($this->storageFile) {
            file_put_contents($this->storageFile, serialize($this->requests));
        }
    }

    /**
     * Loads rate limit data from a persistent storage file.
     */
    private function loadRequests()
    {
        if (file_exists($this->storageFile)) {
            $data = file_get_contents($this->storageFile);
            $this->requests = unserialize($data) ?: new SplQueue();
        }
    }

    /**
     * Saves the rate limit data to persistent storage.
     */
    private function saveRequests()
    {
        if ($this->storageFile) {
            file_put_contents($this->storageFile, serialize($this->requests));
        }
    }
}
