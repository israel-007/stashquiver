<?php
namespace StashQuiver;

use StashQuiver\DataCompressor;

class CacheManager
{
    private $cache = [];
    private $maxSize;
    private $dataCompressor;

    public function __construct($maxSize = null, DataCompressor $dataCompressor = null)
    {
        $this->maxSize = $maxSize;
        $this->dataCompressor = $dataCompressor ?? new DataCompressor();
    }

    /**
     * Stores compressed data in the cache with an expiration time.
     * 
     * @param string $key Unique identifier for the cache entry.
     * @param mixed $data Data to cache (can be any format).
     * @param int $expiration Expiration time in seconds (defaults to 1 hour).
     */
    public function store($key, $data, $expiration = 3600)
    {
        if ($this->dataCompressor) {
            $data = $this->dataCompressor->compress($data);
        }

        // Evict if max size reached
        if ($this->maxSize && count($this->cache) >= $this->maxSize) {
            $this->evictOldest();
        }

        $this->cache[$key] = [
            'data' => $data,
            'expires_at' => time() + $expiration
        ];
    }

    /**
     * Retrieves decompressed cached data by key if it's still valid.
     * 
     * @param string $key The unique identifier for the cache entry.
     * @return mixed|null The cached data if valid, or null if expired/not found.
     */
    public function retrieve($key)
    {
        if (isset($this->cache[$key])) {
            $entry = $this->cache[$key];

            if ($entry['expires_at'] >= time()) {
                return $this->dataCompressor ? $this->dataCompressor->decompress($entry['data']) : $entry['data'];
            }

            // Expired entry, remove it
            unset($this->cache[$key]);
        }

        return null; // Cache miss or expired
    }

    /**
     * Clears a specific cache entry or all entries.
     * 
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
     * Evicts the oldest cache entry if maxSize is reached.
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
