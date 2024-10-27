<?php
namespace StashQuiver;

class CacheManager
{
    private $cache = [];
    private $maxSize;

    public function __construct($maxSize = null)
    {
        $this->maxSize = $maxSize;
    }

    /**
     * @param string $key Unique identifier for the cache entry.
     * @param mixed $data Data to cache (can be any format).
     * @param int $expiration Expiration time in seconds (defaults to 1 hour).
     */
    public function store($key, $data, $expiration = 3600)
    {
        // If cache has maxSize and is at capacity, evict oldest entry (still optional) [ to be reviewed ]
        if ($this->maxSize && count($this->cache) >= $this->maxSize) {
            $this->evictOldest();
        }

        $this->cache[$key] = [
            'data' => $data,
            'expires_at' => time() + $expiration
        ];
    }

    /**
     * @param string $key The unique identifier for the cache entry.
     * @return mixed|null The cached data if valid, or null if expired/not found.
     */
    public function retrieve($key)
    {
        if (isset($this->cache[$key])) {
            $entry = $this->cache[$key];

            if ($entry['expires_at'] >= time()) {
                return $entry['data'];
            }

            // Expired entry, remove it
            unset($this->cache[$key]);
        }

        return null; // Cache miss or expired
    }

    /**
     * @param string|null $key The cache key to clear; clears all if null.
     */
    public function clear($key = null)
    {
        if ($key) {
            unset($this->cache[$key]);
        } else {
            $this->cache = [];
        }
    }

    /**
     * Optionally evicts the oldest cache entry if maxSize is reached.
     */
    private function evictOldest()
    {
        $oldestKey = null;
        $oldestTime = time();

        foreach ($this->cache as $key => $entry) {
            if ($entry['expires_at'] < $oldestTime) {
                $oldestTime = $entry['expires_at'];
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset($this->cache[$oldestKey]);
        }
    }
}
