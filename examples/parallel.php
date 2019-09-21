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
    $loop->addTimer($delay, function () use ($delay, $call_back) {
        $call_back(null, "waited for $delay");
    });
}

function flow()
{
    yield ['wait', 2];
    yield Async::parallel => ['wait', 3];
    yield Async::parallel => ['wait', 5];
    yield Async::all => Async::parallel;
    echo 'finished.' . PHP_EOL;
    return true;
}

function trace(string $key, $value = null)
{
    static $startTime;
    if (!$startTime) {
        $startTime = microtime(true);
    }
    $elapsed = round((microtime(true) - $startTime));
    if ($value) {
        echo sprintf("%04d - %s: %s\n", $elapsed, $key, json_encode($value));
    }
}

function async(Generator $flow, callable $callback)
{
    if (!$flow->valid()) {
        $r = $flow->getReturn();
        $callback(null, $r);
        return;
    }
    $key = $flow->key() ?: Async::await;
    $value = $flow->current();
    $next = function ($error, $result) use ($flow, $key, $callback) {
        $value = $error ?: $result;
        trace($key, $value);
        $flow->send($value);
        async($flow, $callback);
    };
    switch ($key) {
        case Async::parallel:
            if (!isset($flow->parallel)) {
                $flow->parallel = [];
            }
            $flow->parallel[] = $value;
            return $next(null, $value);
        case Async::all:
            if (!isset($flow->parallel)) {
                return $next(null, $value);
            }
            return Async::awaitAll($flow->parallel)->then(
                function ($result) use ($next) {
                    $next(null, $result);
                },
                function ($error) use ($next) {
                    $next($error, null);
                }
            );
    }
    if (is_array($value)) {
        call_user_func($value[0], $value[1], $next);
    } else {
        $next(null, $value);
    }
}

//trace('start');
//async(flow(), function ($error, $result) {
//    var_dump($result);
//});

Async::setLogger(new ConsoleLogger);
Async::await(flow());
$loop->run();
