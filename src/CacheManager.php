<?php
namespace StashQuiver;

use StashQuiver\DataCompressor;
use Redis;
use RuntimeException;

class CacheManager
{
    private $cacheDir;
    private $maxSize;
    private $dataCompressor;
    private $storageBackend;
    private $memoryCache = [];
    private $redis;

    /**
     * Initializes the CacheManager with flexible storage options.
     *
     * @param string $cacheDir The directory where cache files will be stored.
     * @param int|null $maxSize The maximum number of entries allowed in the cache.
     * @param DataCompressor|bool $dataCompressor An optional DataCompressor instance.
     * @param string $storageBackend Storage backend ('file', 'memory', 'redis').
     * @param array|null $redisConfig Redis configuration (host, port, etc.).
     */
    public function __construct($dataCompressor = true, $maxSize = 20, $cacheDir = __DIR__ . '/cache', $storageBackend = 'file', $redisConfig = null)
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
        $this->maxSize = $maxSize;
        $this->dataCompressor = ($dataCompressor) ? new DataCompressor() : false;
        $this->storageBackend = $storageBackend;

        if ($storageBackend === 'file' && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // if ($storageBackend === 'redis' && $redisConfig) {
        //     $this->redis = new Redis();
        //     $this->redis->connect($redisConfig['host'], $redisConfig['port']);
        //     if (isset($redisConfig['auth'])) {
        //         $this->redis->auth($redisConfig['auth']);
        //     }
        // }
    }

    /**
     * Stores data in the selected cache storage with expiration time.
     *
     * @param string $key Unique cache key.
     * @param mixed $data Data to cache.
     * @param int $expiration Expiration time in seconds.
     */
    public function store($key, $data, $expiration = 3600)
    {
        $expiresAt = time() + $expiration;
        $cacheEntry = json_encode(['data' => $data, 'expires_at' => $expiresAt]);

        if ($cacheEntry === false) {
            throw new RuntimeException("Failed to encode cache data for key: $key");
        }

        if ($this->dataCompressor) {
            $cacheEntry = $this->dataCompressor->compress($cacheEntry);
        }

        switch ($this->storageBackend) {
            case 'file':
                file_put_contents($this->getFilePath($key), $cacheEntry);
                $this->evictOldEntries();
                break;
            case 'memory':
                $this->memoryCache[$key] = ['data' => $cacheEntry, 'expires_at' => $expiresAt];
                break;
            case 'redis':
                $this->redis->setex($key, $expiration, $cacheEntry);
                break;
        }
    }

    /**
     * Retrieves cached data if valid.
     *
     * @param string $key The unique cache key.
     * @param bool $raw Whether to return raw compressed data.
     * @return mixed|null Cached data, raw data, or null if expired/not found.
     */
    public function retrieve($key, $raw = false)
    {
        switch ($this->storageBackend) {
            case 'file':
                if (!file_exists($this->getFilePath($key)))
                    return null;
                $cacheEntry = file_get_contents($this->getFilePath($key));
                break;
            case 'memory':
                $cacheEntry = $this->memoryCache[$key]['data'] ?? null;
                break;
            case 'redis':
                $cacheEntry = $this->redis->get($key);
                break;
        }

        if (!$cacheEntry)
            return null;

        if ($this->dataCompressor && !$raw) {
            $cacheEntry = $this->dataCompressor->decompress($cacheEntry);
        }

        $cacheEntry = json_decode($cacheEntry, true);
        if ($cacheEntry === null || $cacheEntry['expires_at'] < time()) {
            $this->clear($key);
            return null;
        }

        return $raw ? $cacheEntry : $cacheEntry['data'];
    }

    /**
     * Checks if a cache entry exists.
     *
     * @param string $key Cache key.
     * @return bool True if cache exists, false otherwise.
     */
    public function exists($key)
    {
        switch ($this->storageBackend) {
            case 'file':
                return file_exists($this->getFilePath($key));
            case 'memory':
                return isset($this->memoryCache[$key]);
            case 'redis':
                return $this->redis->exists($key);
            default:
                return false;
        }
    }

    /**
     * Clears a specific cache entry or all entries.
     *
     * @param string|null $key Cache key (null clears everything).
     */
    public function clear($key = null)
    {
        if ($key) {
            switch ($this->storageBackend) {
                case 'file':
                    unlink($this->getFilePath($key));
                    break;
                case 'memory':
                    unset($this->memoryCache[$key]);
                    break;
                case 'redis':
                    $this->redis->del($key);
                    break;
            }
        } else {
            switch ($this->storageBackend) {
                case 'file':
                    array_map('unlink', glob("{$this->cacheDir}/*"));
                    break;
                case 'memory':
                    $this->memoryCache = [];
                    break;
                case 'redis':
                    $this->redis->flushDB();
                    break;
            }
        }
    }

    /**
     * Evicts old cache entries when maxSize is exceeded (for file-based caching).
     */
    private function evictOldEntries()
    {
        if ($this->storageBackend !== 'file')
            return;

        $files = glob("{$this->cacheDir}/*");
        if (count($files) <= $this->maxSize)
            return;

        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
        foreach (array_slice($files, 0, count($files) - $this->maxSize) as $file) {
            unlink($file);
        }
    }

    /**
     * Generates a unique file path for a cache key.
     *
     * @param string $key Cache key.
     * @return string File path.
     */
    private function getFilePath($key)
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }
}
