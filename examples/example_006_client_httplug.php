<?php

use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
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


function status($url)
{
    $client = HttpAsyncClientDiscovery::find();
    $messageFactory = MessageFactoryDiscovery::find();
    $response = yield $client->sendAsyncRequest(
        $messageFactory->createRequest('GET', $url)
    );
    return $response->getStatusCode();
}

$async = new Async(new EchoLogger());
$async->await(status('http://httplug.io'), 'trace');
$async->await(status('http://httplug.io/missingPage'), 'trace');
