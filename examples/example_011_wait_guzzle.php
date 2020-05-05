<?php

use GuzzleHttp\Client;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;

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

Async::setLogger(new ConsoleLogger);
trace(Async::wait(status('http://httpbin.org/get')));
trace(Async::wait(status('http://httpbin.org/missingPage')));
