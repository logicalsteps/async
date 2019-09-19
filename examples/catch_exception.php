<?php

use LogicalSteps\Async\Async;
use LogicalSteps\Async\ConsoleLogger;

require __DIR__ . '/../vendor/autoload.php';

function blow()
{
    yield;
    yield blast();
}

function blast()
{
    yield;
    throw new Exception('outside');
}

function run()
{
    yield 1;
    try {
        yield 'throw:' . Throwable::class => blow();
    } catch (Throwable $t) {

    }
    return true;
}

//Async::setLogger(new ConsoleLogger);

Async::await(run())->then('var_dump', function (Throwable $t) {
    echo get_class($t) . ': ' . $t->getMessage() . PHP_EOL;
});
