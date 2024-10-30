<?php

require 'vendor/autoload.php';

use StashQuiver\FormatHandler;

$formatHandler = new FormatHandler;
$jsonData = '{"name": "John", "email": "john@example.com"}';
$xmlData = '<user><name>John</name><email>john@example.com</email></user>';
$htmlData = '<html><head><title>Test</title></head><body>Example</body></html>';

echo $formatHandler->validate($jsonData, 'json') ? "Valid JSON\n" : "Invalid JSON\n";
echo $formatHandler->validate($xmlData, 'xml') ? "Valid XML\n" : "Invalid XML\n";
echo $formatHandler->validate($htmlData, 'html') ? "Valid HTML\n" : "Invalid HTML\n";
