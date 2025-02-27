<?php

require 'vendor/autoload.php';

use StashQuiver\Requests;

$request = new Requests();

echo $request
->url('https://catfact.ninja/fact')
->useCache(false)
->rateLimiter([20, 60])
->execute();

