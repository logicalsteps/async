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
    return true;
}

Async::setLogger(new ConsoleLogger);
//Async::await(flow());

function trace(string $key, $value)
{
    echo sprintf("%s: %s\n", $key, json_encode($value));
}

function runFor(Generator $f)
{
    foreach ($f as $key => $value) {
        trace($key, $value);
    }
    trace('return', $f->getReturn());
}

function runManual(Generator $f)
{
    trace($f->key(), $f->current());
    while ($f->valid()) {
        $value = $f->send(null);
        if ($f->valid()) {
            trace($f->key(), $value);
        }
    }
    trace('return', $f->getReturn());
}

function runRecursive(Generator $f)
{
    if (!$f->valid()) {
        trace('return', $f->getReturn());
        return;
    }
    trace($f->key(), $f->current());
    $f->send(null);
    runRecursive($f);
}

runFor(flow());
echo '-----------------------' . PHP_EOL;
runManual(flow());
echo '-----------------------' . PHP_EOL;
runRecursive(flow());
$loop->run();
