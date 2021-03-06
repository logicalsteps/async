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
trace($async->wait(status('http://httplug.io')));
Async::setLogger(new ConsoleLogger());
trace(Async::wait(status('http://httplug.io/missingPage')));
