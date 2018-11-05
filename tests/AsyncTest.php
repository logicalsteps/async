<?php

namespace LogicalSteps\Async\Test;

use Amp\Success as AmpSuccess;
use Amp\Failure as AmpFailure;
use Error;
use Exception;
use GuzzleHttp\Promise\FulfilledPromise as GuzzleFulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise as GuzzleRejectedPromise;
use Http\Promise\FulfilledPromise as HttplugFulfilledPromise;
use Http\Promise\RejectedPromise as HttplugRejectedPromise;
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
        $callable = function (callable $callback) {
            $callback(null, 14);
        };

        $promise = Async::await($callable);
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

    public function testAwaitForFailedReactPromise()
    {
        $knownPromise = new ReactPromise(function ($resolver, $canceller) {
            $canceller('failed react_promise');
        });

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals($error, 'failed react_promise');
        });
        $this->assertPromiseRejects($promise);
    }

    public function testAwaitForAmpPromise()
    {
        $knownPromise = new AmpSuccess('amp_promise');

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 'amp_promise');
    }

    public function testAwaitForFailedAmpPromise()
    {
        $knownPromise = new AmpFailure(new Error('failed amp_promise'));

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals($error, 'failed amp_promise');
        });
        $this->assertPromiseRejects($promise);
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

    public function testAwaitForFailedGuzzlePromise()
    {
        $knownPromise = new GuzzleRejectedPromise('failed guzzle_promise');

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals($error, 'failed guzzle_promise');
        });
        $this->assertPromiseRejects($promise);
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

    public function testAwaitForFailedHttplugPromise()
    {
        $knownPromise = new HttplugRejectedPromise(new Exception('failed httplug_promise'));

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals($error, 'failed httplug_promise');
        });
        $this->assertPromiseRejects($promise);
    }

}
