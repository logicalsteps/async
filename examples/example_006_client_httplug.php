<?php

use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;

require __DIR__ . '/../vendor/autoload.php';

function trace($response)
{
    echo json_encode($response) . PHP_EOL;
}


function status($url)
{
    $client = HttpAsyncClientDiscovery::find();
    $messageFactory = MessageFactoryDiscovery::find();
    $response = yield $client->sendAsyncRequest(
        $messageFactory->createRequest('GET', $url)
    );
    return $response->getStatusCode();
}

$async = new Async(new ConsoleLogger());
$async->await(status('http://httplug.io'))->then('trace');
$async->await(status('http://httplug.io/missingPage'))->then('trace');

$async->awaitAll([status('http://httplug.io'), status('http://httplug.io/missingPage')])->then('trace');
