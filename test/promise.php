<?php

use GuzzleHttp\Promise\Promise;
use LogicalSteps\Async\Async2;

include __DIR__ . '/bootstrap.php';

$line = function () {
    echo '-------------------------------------' . PHP_EOL;
};

$async = new Async2();

$guzzle_promise = new Promise();
$react_promise = $async->_handlePromise($guzzle_promise);
$react_promise->then('var_dump', 'var_dump')->then($line);
$guzzle_promise->resolve(123);

echo '-------------------------------------' . PHP_EOL;

$callable = function (callable $fn) {
    $fn(null, 456);
};

$react_promise = $async->_handleCallback($callable)->then('var_dump', 'var_dump')->then($line);

class Temp
{

    public static function func(callable $fn)
    {
        $fn(null, 789);
    }
}


$react_promise = $async->_handleCallback(['Temp', 'func'])->then('var_dump', 'var_dump')->then($line);
