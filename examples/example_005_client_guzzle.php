<?php

use GuzzleHttp\Client;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;

require __DIR__ . '/../vendor/autoload.php';

function trace($response)
{
    echo json_encode($response) . PHP_EOL;
}

function trace_callback($error, $response)
{
    trace($response);
}


function status($url)
{
    $client = new Client(['http_errors' => false]);
    $response = yield $client->getAsync($url);
    return $response->getStatusCode();
}

Async::setLogger(new ConsoleLogger);
Async::await(status('http://httpbin.org/get'))->then('trace');
Async::await(status('http://httpbin.org/missingPage'), 'trace_callback');

Async::awaitAll([status('http://httpbin.org/get'), status('http://httpbin.org/missingPage')])->then('trace');
