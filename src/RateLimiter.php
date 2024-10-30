<?php
namespace StashQuiver;

class RateLimiter
{
    private $limit;
    private $window;
    private $requestTimestamps = [];

    /**
     * Initializes the RateLimiter with a limit and time window.
     *
     * @param int $limit The maximum number of requests allowed.
     * @param int $window The time window in seconds.
     */
    public function __construct($limit = 60, $window = 60)
    {
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Checks if a request is allowed based on the rate limit.
     *
     * @return bool True if the request is allowed; false if rate limit is exceeded.
     */
    public function allowRequest()
    {
        $currentTime = time();

        // Remove timestamps that are outside the current window
        $this->cleanUpOldRequests($currentTime);

        if (count($this->requestTimestamps) < $this->limit) {
            $this->incrementRequestCount($currentTime);
            return true;
        }

        return false;
    }

    /**
     * Increments the request count for the current time window.
     *
     * @param int $timestamp The current timestamp.
     */
    private function incrementRequestCount($timestamp)
    {
        $this->requestTimestamps[] = $timestamp;
    }

    /**
     * Removes timestamps outside the current time window.
     *
     * @param int $currentTime The current timestamp.
     */
    private function cleanUpOldRequests($currentTime)
    {
        $this->requestTimestamps = array_filter(
            $this->requestTimestamps,
            function ($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) < $this->window;
            }
        );
    }
}
