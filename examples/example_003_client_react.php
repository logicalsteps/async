<?php

use Clue\React\Buzz\Browser;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;
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
    return $response->getStatusCode();
};

$async = new Async(null,$loop);
$promise = $browser->get('http://httpbin.org/get');
$value = $async->wait($promise);

Async::setLogger(new ConsoleLogger());
Async::await($status('http://httpbin.org/get'))->then('trace');
Async::await($status('http://httpbin.org/missingPage'))->then('trace');

//Async::awaitAll([$status('http://httpbin.org/get'), $status('http://httpbin.org/missingPage')])->then('trace');

$loop->run();
