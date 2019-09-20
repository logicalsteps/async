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

function step(string $key, $value, Generator $f, callable $callback)
{
    switch ($key) {
        case Async::parallel:
            if (!isset($f->parallel)) {
                $f->parallel = [];
            }
            $f->parallel[] = $value;
            return $callback(null, $value);
        case Async::all:
            if (!isset($f->parallel)) {
                $callback(null, $value);
            }
            return Async::awaitAll($f->parallel)->then(
                function ($result) use ($callback) {
                    $callback(null, $result);
                },
                function ($error) use ($callback) {
                    $callback($error, null);
                }
            );
    }
    if (is_array($value)) {
        call_user_func($value[0], $value[1], $callback);
    } else {
        $callback(null, $value);
    }
}

function async(Generator $f, callable $callback)
{
    if (!$f->valid()) {
        trace('return', $r = $f->getReturn());
        $callback(null, $r);
        return;
    }
    $key = $f->key() ?: Async::await;
    $value = $f->current();
    $next = function ($error, $result) use ($f, $key, $callback) {
        $value = $error ?: $result;
        trace($key, $value);
        $f->send($value);
        async($f, $callback);
    };
    step($key, $value, $f, $next);
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
