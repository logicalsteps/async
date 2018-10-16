<?php

use Amp\Artax\DefaultClient;
use Amp\Loop;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\EchoLogger;

require __DIR__ . '/../vendor/autoload.php';


function trace($error, $response)
{
    if ($error) {
        echo (string)$error . PHP_EOL;
        return;
    }
    echo json_encode($response) . PHP_EOL;
}

$client = new DefaultClient();

$status = function ($url) use ($client) {
    $response = yield $client->request($url);
    return $response->getStatus();
};
$async = new Async(new EchoLogger());
$async->useAmpLoop();
$async->execute($status('http://httpbin.org/get'), 'trace');
$async->execute($status('http://httpbin.org/missingPage'), 'trace');

Loop::run();