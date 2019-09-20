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

Async::setLogger(new ConsoleLogger);
//Async::await(flow());

function trace(string $key, $value)
{
    echo sprintf("%s: %s\n", $key, json_encode($value));
}

function step(string $key, $value, Generator $flow, callable $next)
{
    switch ($key) {
        case Async::parallel:
            if (!isset($flow->parallel)) {
                $flow->parallel = [];
            }
            $flow->parallel[] = $value;
            return $next(null, $value);
        case Async::all:
            if (!isset($flow->parallel)) {
                $next(null, $value);
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

function async(Generator $flow, callable $callback)
{
    if (!$flow->valid()) {
        trace('return', $r = $flow->getReturn());
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
    step($key, $value, $flow, $next);
}

/*
runFor(flow());
echo '-----------------------' . PHP_EOL;
runManual(flow());
echo '-----------------------' . PHP_EOL;
runRecursive(flow());
echo '-----------------------' . PHP_EOL;
*/
async(flow(), function ($error, $result) {
    var_dump($result);
});
$loop->run();
