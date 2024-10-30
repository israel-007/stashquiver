<?php

require 'vendor/autoload.php';

use StashQuiver\DataCompressor;

$dataCompressor = new DataCompressor;

// Original data (e.g., an API response)
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'roles' => ['admin', 'editor']
];

// Compress the data
$compressedData = $dataCompressor->compress($data);
echo "Compressed Data: " . $compressedData . "\n";

// Decompress the data
$decompressedData = $dataCompressor->decompress($compressedData);
print_r($decompressedData);
