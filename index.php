<?php

require 'vendor/autoload.php';

use StashQuiver\ApiRequestBuilder;

$request = new ApiRequestBuilder();

echo ($request->url('https://catfact.ninja/fact')
            ->send());


