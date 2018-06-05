<?php
require __DIR__ . '/../vendor/autoload.php';

$refresh = (isset($argv[1]))? $argv[1] : false;
$devices = \openWebX\PhpWemo\Discovery::find($refresh);

$insight = \openWebX\PhpWemo\Discovery::getDeviceById('insight');
$params = $insight->getParams();
$parts = explode('|', $params);

print 'InsightParams: ' . $params . PHP_EOL .
      'Current power draw of Insight switch: ' . $parts[7] . PHP_EOL;
