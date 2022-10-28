<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use NSRosenqvist\APIcly\API;

$client = new Client();
$api = new API($client, ['base_url' => 'https://httpbin.org']);

$promise = $api->get->getAsync();
$promise->wait();
$result = $promise->wait();

var_dump($result);

// echo $result->code . PHP_EOL;
$promise = $api->get->getAsync();
$result = $promise->wait();
// echo $result->code . PHP_EOL;


// $result->send();