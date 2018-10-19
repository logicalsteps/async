<?php

use Amp\Artax\DefaultClient;
use Amp\Loop;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\EchoLogger;

require __DIR__ . '/../vendor/autoload.php';


function trace($response)
{
    echo json_encode($response) . PHP_EOL;
}

$client = new DefaultClient();

$status = function ($url) use ($client) {
    $response = yield $client->request($url);
    return $response->getStatus();
};
$async = new Async(new EchoLogger());
$async->useAmpLoop();
$async->await($status('http://httpbin.org/get'))->then('trace');
$async->await($status('http://httpbin.org/missingPage'))->then('trace');

Loop::run();