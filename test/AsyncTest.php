<?php

namespace LogicalSteps\Async\Test;

use LogicalSteps\Async\Async;
use React\Promise\PromiseInterface;
use seregazhuk\React\PromiseTesting\TestCase;

class AsyncTest extends TestCase
{

    public function testAwaitForSynchronousValue()
    {
        $promise = Async::await(7);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 7);
    }

    public function testAwaitForCallback()
    {
        $later = function (callable $callback) {
            sleep(1);
            $callback(null, 14);
        };

        $promise = Async::await($later);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 14);
    }

    public function testAwaitForGenerator()
    {
        $gen = function () {
            return yield 28;
        };

        $promise = Async::await($gen);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 28);
    }

    /*
    public function testSetLogger()
    {

    }
    */
}
