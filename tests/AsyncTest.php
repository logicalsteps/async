<?php

namespace LogicalSteps\Async\Test;

use Amp\Success as AmpSuccess;
use Amp\Failure as AmpFailure;
use Clue\React\Buzz\Browser;
use Error;
use Exception;
use GuzzleHttp\Promise\FulfilledPromise as GuzzleFulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise as GuzzleRejectedPromise;
use Http\Promise\FulfilledPromise as HttplugFulfilledPromise;
use Http\Promise\RejectedPromise as HttplugRejectedPromise;
use LogicalSteps\Async\Async;
use Psr\Log\NullLogger;
use React\EventLoop\Factory;
use React\Promise\Promise as ReactPromise;
use React\Promise\PromiseInterface;
use function foo\func;

class AsyncTest extends TestCase
{
    public function setUp(): void
    {
        Async::setLogger(new NullLogger);
        parent::setUp();
    }

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

    public function testAwaitForFailedCallback()
    {
        $callable = function (callable $callback) {
            $callback('failed callback');
        };

        $promise = Async::await($callable);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals('failed callback', $error);
        });
        $this->assertPromiseRejects($promise);
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

    public function testAwaitForGeneratorInsideGeneratorWithAnInstance()
    {
        function gen2()
        {
            $num = yield gen();
            return 2 * $num;
        }

        $async = new Async(new NullLogger);
        $promise = $async->await(gen2());
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $this->assertPromiseFulfillsWith($promise, 56);
    }

    public function testAwaitForGeneratorException()
    {
        function genF()
        {
            throw new Exception('failed generator');
            return yield 28;
        }

        $promise = Async::await(genF());
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals('failed generator', $error);
        });
        $this->assertPromiseRejects($promise);
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
            $this->assertEquals('failed react_promise', $error);
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
            $this->assertEquals('failed amp_promise', $error);
        });
        $this->assertPromiseRejects($promise);
    }

    public function testAwaitForGuzzlePromise()
    {
        $knownPromise = new GuzzleFulfilledPromise('guzzle_promise');

        $promise = Async::await($knownPromise);
        $promise->then(function ($value) {
            $this->assertEquals('guzzle_promise', $value);
        });
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testAwaitForFailedGuzzlePromise()
    {
        $knownPromise = new GuzzleRejectedPromise('failed guzzle_promise');

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals('failed guzzle_promise', $error);
        });
        $this->assertPromiseRejects($promise);
    }

    public function testAwaitForHttplugPromise()
    {
        $knownPromise = new HttplugFulfilledPromise('httplug_promise');

        $promise = Async::await($knownPromise);
        $promise->then(function ($value) {
            $this->assertEquals('httplug_promise', $value);
        });
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function testAwaitForFailedHttplugPromise()
    {
        $knownPromise = new HttplugRejectedPromise(new Exception('failed httplug_promise'));

        $promise = Async::await($knownPromise);
        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $promise->then(function ($value, $error) {
            $this->assertEquals('failed httplug_promise', $error);
        });
        $this->assertPromiseRejects($promise);
    }

    public function testAwaitAllForReactPromises()
    {
        $loop = Factory::create();
        $browser = (new Browser($loop))->withOptions(['streaming' => true, 'obeySuccessCode' => false]);

        $status = function ($url) use ($browser) {
            $response = yield $browser->get($url);
            return $response->getStatusCode();
        };
        Async::awaitAll(
            [$status('http://httpbin.org/get'), $status('http://httpbin.org/missingPage')],
            function ($error = null, $result = null) use ($loop) {
                $this->assertEquals(2, count($result));
                $loop->stop();
            }
        );

        $loop->run();
    }

}
