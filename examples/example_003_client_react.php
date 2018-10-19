<?php

use Clue\React\Buzz\Browser;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\EchoLogger;
use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

function trace($error, $response)
{
    if ($error) {
        echo (string)$error . PHP_EOL;
        return;
    }
    echo json_encode($response) . PHP_EOL;
}

$loop = Factory::create();
$browser = (new Browser($loop))->withOptions(['streaming' => true, 'obeySuccessCode' => false]);

$status = function ($url) use ($browser) {
    $response = yield $browser->get($url);
    return $response->getStatusCode();
};

$async = new Async(new EchoLogger());
$async->setLoop($loop);
$async->await($status('http://httpbin.org/get'), 'trace');
$async->await($status('http://httpbin.org/missingPage'), 'trace');

$loop->run();