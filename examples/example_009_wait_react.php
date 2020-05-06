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

$async = new Async(new ConsoleLogger, $loop);
trace($async->wait($status('http://httpbin.org/get')));

Async::setLogger(new ConsoleLogger);
Async::setEventLoop($loop);
trace(Async::wait($status('http://httpbin.org/missingPage')));

$loop->run();
