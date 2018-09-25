<?php

use LogicalSteps\Async\Async;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

require __DIR__ . '/bootstrap.php';

/** @var LoopInterface $loop */
$loop = Factory::create();

function flow()
{
    echo 'started' . PHP_EOL;
    yield 'two_seconds';
    echo 'after two seconds' . PHP_EOL;
    yield 'four_seconds';
    echo 'after four seconds' . PHP_EOL;
    yield ['Timer', 'delay', 8];
    echo 'after eight seconds' . PHP_EOL;
    yield [new Timer(), 'delay', 3];
    echo 'after three seconds' . PHP_EOL;

    return 'completed';
}

function two_seconds(callable $call_back)
{
    global $loop;
    $loop->addTimer(2, function () use ($call_back) {
        $call_back(null, true);
    });
}

function four_seconds(callable $call_back)
{
    global $loop;
    $loop->addTimer(4, function () use ($call_back) {
        $call_back(null, true);
    });
}

class Timer
{
    static function delay(int $seconds, callable $call_back)
    {
        global $loop;
        $loop->addTimer($seconds, function () use ($call_back) {
            $call_back(null, true);
        });
    }

    function wait(int $seconds, callable $call_back)
    {
        global $loop;
        $loop->addTimer($seconds, function () use ($call_back) {
            $call_back(null, true);
        });
    }
}


$async = new Async();
$async->execute(flow(), 'var_dump');

$loop->run();

