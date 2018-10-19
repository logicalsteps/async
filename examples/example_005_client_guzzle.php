<?php

use GuzzleHttp\Client;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\EchoLogger;

require __DIR__ . '/../vendor/autoload.php';

function trace($response)
{
    echo json_encode($response) . PHP_EOL;
}


function status($url)
{
    $client = new Client(['http_errors' => false]);
    $response = yield $client->getAsync($url);
    return $response->getStatusCode();
}

$async = new Async(new EchoLogger());
$async->await(status('http://httpbin.org/get'))->then('trace');
$async->await(status('http://httpbin.org/missingPage'))->then('trace');
