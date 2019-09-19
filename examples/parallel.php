<?php

use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

require __DIR__ . '/../vendor/autoload.php';

/** @var LoopInterface $loop */
$loop = Factory::create();

function wait(int $delay, callable $call_back)
{
    global $loop;
    $loop->addTimer($delay, function () use ($call_back) {
        $call_back(null, true);
    });
}

function flow()
{
    yield ['wait', 2];
    yield Async::parallel() => ['wait', 3];
    yield Async::parallel() => ['wait', 5];
    yield Async::all() => null;
    echo 'all completed.' . PHP_EOL;
}

Async::setLogger(new ConsoleLogger);
Async::await(flow());

$loop->run();
