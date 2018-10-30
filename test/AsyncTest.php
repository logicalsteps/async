<?php

namespace LogicalSteps\Async\Test;

use Amp\Success as AmpSuccess;
use GuzzleHttp\Promise\FulfilledPromise as GuzzleFulfilledPromise;
use Http\Promise\FulfilledPromise as HttplugFulfilledPromise;
use LogicalSteps\Async\Async;
use React\Promise\Promise as ReactPromise;
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
        function gen()
        {
            return yield 28;
        }

        $promise = Async::await(gen());
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 28);
    }

    public function testAwaitForReactPromise()
    {
        $knownPromise = new ReactPromise(function ($resolver) {
            $resolver('react_promise');
        });

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 'react_promise');
    }

    public function testAwaitForAmpPromise()
    {
        $knownPromise = new AmpSuccess('amp_promise');

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 'amp_promise');
    }

    public function testAwaitForGuzzlePromise()
    {
        $knownPromise = new GuzzleFulfilledPromise('guzzle_promise');

        $promise = Async::await($knownPromise);
        $promise->then(function ($value) {
            $this->assertEquals($value, 'guzzle_promise');
        });
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testAwaitForHttplugPromise()
    {
        $knownPromise = new HttplugFulfilledPromise('httplug_promise');

        $promise = Async::await($knownPromise);
        $promise->then(function ($value) {
            $this->assertEquals($value, 'httplug_promise');
        });
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    /*
    public function testSetLogger()
    {

    }
    */
}
