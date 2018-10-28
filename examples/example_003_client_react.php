<?php

use Clue\React\Buzz\Browser;
use LogicalSteps\Async\Async2 as Async;
use LogicalSteps\Async\EchoLogger;
use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

function trace($response)
{
    echo json_encode($response) . PHP_EOL;
}

$loop = Factory::create();
$browser = (new Browser($loop))->withOptions(['streaming' => true, 'obeySuccessCode' => false]);

$status = function ($url) use ($browser) {
    $response = yield $browser->get($url);
    $code = $response->getStatusCode();
    return $code; //$response->getStatusCode();
};

$async = new Async(new EchoLogger());
$async->setLoop($loop);
$async->await($status('http://httpbin.org/get'))->then('trace');
$async->await($status('http://httpbin.org/missingPage'))->then('trace');

$loop->run();