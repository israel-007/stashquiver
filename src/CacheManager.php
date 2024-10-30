<?php
namespace StashQuiver;

use StashQuiver\DataCompressor;

class CacheManager
{
    private $cacheDir;
    private $maxSize;
    private $dataCompressor;

    /**
     * Initializes the CacheManager with file-based caching.
     * 
     * @param string $cacheDir The directory where cache files will be stored.
     * @param int|null $maxSize The maximum number of entries allowed in the cache.
     * @param DataCompressor|bool $dataCompressor An optional DataCompressor instance for compressing cached data.
     */
    public function __construct($dataCompressor = true, $maxSize = 20, $cacheDir = __DIR__ . '/cache')
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        $this->maxSize = $maxSize;
        $this->dataCompressor = ($dataCompressor) ? new DataCompressor() : false;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Stores data in a file-based cache with an expiration time.
     * 
     * @param string $key Unique identifier for the cache entry.
     * @param mixed $data Data to cache.
     * @param int $expiration Expiration time in seconds.
     */
    public function store($key, $data, $expiration = 3600)
    {
        $filePath = $this->getFilePath($key);
        $expiresAt = time() + $expiration;

        // Prepare the cache entry as JSON before compression
        $cacheEntry = json_encode(['data' => $data, 'expires_at' => $expiresAt]);
        if ($cacheEntry === false) {
            throw new \RuntimeException("Failed to encode cache data for key: $key");
        }

        // Compress the JSON-encoded cache entry if DataCompressor is enabled
        if ($this->dataCompressor) {
            $cacheEntry = $this->dataCompressor->compress($cacheEntry);
            if ($cacheEntry === false) {
                throw new \RuntimeException("Failed to compress data for key: $key");
            }
        }

        // Write the compressed data to the cache file
        $result = file_put_contents($filePath, $cacheEntry);
        if ($result === false) {
            throw new \RuntimeException("Failed to write cache file for key: $key at path: $filePath");
        }

        // Evict old entries if maxSize is defined
        if ($this->maxSize) {
            $this->evictOldEntries();
        }
    }

    /**
     * Retrieves cached data from a file if it exists and is not expired.
     * 
     * @param string $key The unique identifier for the cache entry.
     * @return mixed|null The cached data if valid, or null if expired/not found.
     */
    public function retrieve($key)
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null; // Cache miss
        }

        // Read the file and decompress if necessary
        $cacheEntry = file_get_contents($filePath);
        if ($this->dataCompressor) {
            $cacheEntry = $this->dataCompressor->decompress($cacheEntry);
            if ($cacheEntry === false) {
                unlink($filePath); // Remove corrupted entry
                return null;
            }
        }

        // Decode the JSON data after decompression
        $cacheEntry = json_decode($cacheEntry, true);
        if ($cacheEntry === null || !isset($cacheEntry['expires_at']) || $cacheEntry['expires_at'] < time()) {
            unlink($filePath); // Remove expired or invalid entry
            return null;
        }

        return $cacheEntry['data'];
    }

    /**
     * Clears a specific cache entry or all entries in the cache directory.
     * 
     * @param string|null $key The cache key to clear; clears all if null.
     */
    public function clear($key = null)
    {
        if ($key) {
            $filePath = $this->getFilePath($key);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } else {
            array_map('unlink', glob("{$this->cacheDir}/*"));
        }
    }

    /**
     * Evicts oldest entries if the maxSize limit is reached.
     */
    private function evictOldEntries()
    {
        $files = glob("{$this->cacheDir}/*");

        if (count($files) <= $this->maxSize) {
            return;
        }

        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $filesToDelete = array_slice($files, 0, count($files) - $this->maxSize);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }

    /**
     * Generates a unique file path for a given cache key.
     * 
     * @param string $key The cache key.
     * @return string The file path for storing the cache entry.
     */
    private function getFilePath($key)
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}
