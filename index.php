<?php

require 'vendor/autoload.php';

use StashQuiver\Requests;

$request = new Requests();

echo $request->url('https://catfact.ninja/fact')->send();

