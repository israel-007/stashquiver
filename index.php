<?php

require 'vendor/autoload.php';

use StashQuiver\CacheManager;

$cache = new CacheManager();
$cache->store('user_123', ['name' => 'John Doe', 'email' => 'john@example.com'], 600);

$userData = $cache->retrieve('user_123');

if ($userData) {

    echo "Cached data: " . print_r($userData, true);

} else {

    echo "No cache found or data expired.";
    
}

$cache->clear('user_123'); // to clear single cache

$cache->clear(); // to clear all cache


