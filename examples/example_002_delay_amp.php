<?php

use Amp\Deferred;
use Amp\Loop;
use LogicalSteps\Async\Async;
use LogicalSteps\Async\EchoLogger;

require __DIR__ . '/../vendor/autoload.php';

function two_seconds(callable $call_back)
{
    Loop::delay(2000, function () use ($call_back) {
        $call_back(null, true);
    });
}

class Timer
{
    static function delay(int $seconds, callable $call_back)
    {
        Loop::delay($seconds * 1000, function () use ($call_back) {
            $call_back(null, true);
        });
    }

    function wait(int $seconds, callable $call_back)
    {
        Loop::delay($seconds * 1000, function () use ($call_back) {
            $call_back(null, true);
        });
    }

    function hold(int $seconds)
    {
        yield [$this, 'wait', $seconds];

        return true;
    }

    function promise(int $seconds)
    {
        $differed = new Deferred();
        Loop::delay($seconds * 1000, function () use ($differed, $seconds) {
            $differed->resolve($seconds);
        });

        return $differed->promise();
    }
}

function flow()
{
    //echo 'started' . PHP_EOL;
    yield 'two_seconds';
    //echo 'after two seconds' . PHP_EOL;
    yield ['Timer', 'delay', 8];
    //echo 'after eight seconds' . PHP_EOL;
    $timer = new Timer();
    yield [$timer, 'wait', 3];
    //echo 'after three seconds' . PHP_EOL;
    yield $timer->hold(1);
    //echo 'after one second' . PHP_EOL;
    yield $timer->promise(2);
    //echo 'after two seconds' . PHP_EOL;
    return yield $timer->hold(7);
}

$async = new Async(new EchoLogger());
//$async->useAmpLoop();
$async->await(flow());
//$async->execute(flow()); //run another session in parallel

Loop::run();

