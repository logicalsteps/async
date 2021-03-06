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

$async = new Async(new ConsoleLogger(), Loop::get());
trace($async->wait($status('http://httpbin.org/get')));

Async::setLogger(new ConsoleLogger());
Async::setEventLoop(Loop::get());
trace(Async::wait($status('http://httpbin.org/missingPage')));

Loop::run();
