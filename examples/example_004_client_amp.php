<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Loop;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;

require __DIR__ . '/../vendor/autoload.php';


function trace($response)
{
    echo json_encode($response) . PHP_EOL;
}

$client = HttpClientBuilder::buildDefault();

$status = function ($url) use ($client) {
    $response = yield $client->request(new Request($url));
    return $response->getStatus();
};
$async = new Async(new ConsoleLogger());
$async->await($status('http://httpbin.org/get'))->then('trace');
$async->await($status('http://httpbin.org/missingPage'))->then('trace');

$async->awaitAll([$status('http://httpbin.org/get'), $status('http://httpbin.org/missingPage')])->then('trace');

Loop::run();
