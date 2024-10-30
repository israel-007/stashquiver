<?php
namespace StashQuiver;

class DataCompressor
{
    /**
     * Compresses data using gzip compression.
     * 
     * @param mixed $data The data to compress.
     * @return string The compressed data.
     * @throws \RuntimeException if compression fails.
     */
    public function compress($data)
    {
        $compressed = gzencode(serialize($data));

        if ($compressed === false) {
            throw new \RuntimeException("Failed to compress data.");
        }

        return $compressed;
    }

    /**
     * Decompresses gzip-compressed data.
     * 
     * @param string $data The compressed data to decompress.
     * @return mixed The decompressed data.
     * @throws \RuntimeException if decompression fails.
     */
    public function decompress($data)
    {
        $decompressed = gzdecode($data);

        if ($decompressed === false) {
            throw new \RuntimeException("Failed to decompress data.");
        }

        return unserialize($decompressed);
    }
}
