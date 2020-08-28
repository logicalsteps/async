<?php

namespace LogicalSteps\Async\Test;

use Exception;
use LogicalSteps\Async\Async;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Promise\Timer\TimeoutException;

class TestCase extends PHPUnitTestCase
{
    private const DEFAULT_WAIT_TIMEOUT = 2;

    private $loop;

    /**
     * @param PromiseInterface $promise
     * @param int|null $timeout seconds to wait for resolving
     * @throws AssertionFailedError
     */
    public function assertPromiseFulfills($promise, int $timeout = null): void
    {
        $failMessage = 'Failed asserting that promise fulfills. ';
        $this->addToAssertionCount(1);
        try {
            $this->waitForPromise($promise, $timeout);
        } catch (TimeoutException $exception) {
            $this->fail($failMessage . 'Promise was cancelled due to timeout.');
        } catch (Exception $exception) {
            $this->fail($failMessage . 'Promise was rejected.');
        }
    }

    /**
     * @param $promise
     * @param int|null $timeout
     * @return mixed
     */
    public function waitForPromise($promise, int $timeout = null)
    {
        if ($timeout) {
            echo 'adding timeout ' . $timeout . PHP_EOL;
            $this->loop->addTimer(
                $timeout,
                function () use ($promise, $timeout) {
                    var_dump($timeout);
                    $promise->reject(new TimeoutException($timeout . ' seconds has passed.'));
                }
            );
        }
        $async = new Async(null, $this->loop);
        $value = $async->wait($promise);
        return $value;
    }

    /**
     * @param PromiseInterface $promise
     * @param mixed $value
     * @param int|null $timeout
     * @throws AssertionFailedError
     */
    public function assertPromiseFulfillsWith($promise, $value, int $timeout = null): void
    {
        $failMessage = 'Failed asserting that promise fulfills with a specified value. ';
        $result = null;
        $this->addToAssertionCount(1);

        try {
            $result = $this->waitForPromise($promise, $timeout);
        } catch (TimeoutException $exception) {
            $this->fail($failMessage . 'Promise was cancelled due to timeout.');
        } catch (Exception $exception) {
            $this->fail($failMessage . 'Promise was rejected.');
        }

        $this->assertEquals($value, $result, $failMessage);
    }

    /**
     * @throws AssertionFailedError
     */
    public function assertPromiseFulfillsWithInstanceOf(
        $promise,
        string $class,
        int $timeout = null
    ): void {
        $failMessage = "Failed asserting that promise fulfills with a value of class $class. ";
        $result = null;
        $this->addToAssertionCount(1);

        try {
            $result = $this->waitForPromise($promise, $timeout);
        } catch (TimeoutException $exception) {
            $this->fail($failMessage . 'Promise was cancelled due to timeout.');
        } catch (Exception $exception) {
            $this->fail($failMessage . 'Promise was rejected.');
        }

        $this->assertInstanceOf($class, $result, $failMessage);
    }

    /**
     * @param PromiseInterface $promise
     * @param int|null $timeout
     * @throws AssertionFailedError
     */
    public function assertPromiseRejects($promise, int $timeout = null): void
    {
        $this->addToAssertionCount(1);

        try {
            $this->waitForPromise($promise, $timeout);
        } catch (Exception $exception) {
            return;
        }

        $this->fail('Failed asserting that promise rejects. Promise was fulfilled.');
    }

    /**
     * @param PromiseInterface $promise
     * @param string $reasonExceptionClass
     * @param int|null $timeout
     */
    public function assertPromiseRejectsWith(
        $promise,
        string $reasonExceptionClass,
        int $timeout = null
    ): void {
        try {
            $this->waitForPromise($promise, $timeout);
        } catch (Exception $reason) {
            $this->assertInstanceOf(
                $reasonExceptionClass,
                $reason,
                'Failed asserting that promise rejects with a specified reason.'
            );
        }

        $this->fail('Failed asserting that promise rejects. Promise was fulfilled.');
    }

    /**
     * @param PromiseInterface $promise
     * @param int|null $timeout
     * @return mixed
     */
    public function waitForPromiseToFulfill($promise, int $timeout = null)
    {
        try {
            return $this->waitForPromise($promise, $timeout);
        } catch (Exception $exception) {
            $reason = get_class($exception);
            $this->fail("Failed to fulfill a promise. It was rejected with {$reason}.");
        }
    }

    public function eventLoop(): LoopInterface
    {
        return $this->loop;
    }

    protected function setUp(): void
    {
        $this->loop = LoopFactory::create();
    }
}
