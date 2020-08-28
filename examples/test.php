<?php

require __DIR__ . '/../vendor/autoload.php';

use LogicalSteps\Async\Async;
use LogicalSteps\Async\Promise;
use React\EventLoop\Factory;

//Async::$promiseClass = \React\Promise\Promise::class;
$loop = Factory::create();
$executor = function ($resolver, $rejector) use ($loop) {

    $loop->addTimer(2, fn() => $rejector('done'));
};

$promise = (new Promise($executor));

$p2 = $promise->then(
    null,
    function ($error) {
        return 'all ' . $error;
    }
);

$loop->run();

exit();

$value = Async::wait(7);
$promise = Async::await(7);
$implements = class_implements($promise) + [get_class($promise)];
//$implements[] = get_class($promise);
$success = is_object($promise) && $implements = array_intersect(
        $implements,
        Async::$knownPromises
    );
var_dump(Async::$promiseClass === get_class($promise));
$value = Async::wait($promise);
var_dump($value === 7);
