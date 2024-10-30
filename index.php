<?php

require 'vendor/autoload.php';

use StashQuiver\FormatHandler;

use StashQuiver\CacheManager;
use StashQuiver\DataCompressor;

$dataCompressor = new DataCompressor();
$cacheManager = new CacheManager();

// Sample data to cache
$data = ['name' => 'John Doe', 'email' => 'john@example.com'];
$cacheManager->store('user_data', $data, 3600); // Expire after 1 hour


$retrievedData = $cacheManager->retrieve('user_data');
print_r($retrievedData);


