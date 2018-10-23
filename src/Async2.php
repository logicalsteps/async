<?php

namespace LogicalSteps\Async;


use Generator;
use ArgumentCountError;
use InvalidArgumentException;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class Async2
{
    const PROMISE_REACT = 'React\Promise\PromiseInterface';
    const PROMISE_GUZZLE = 'GuzzleHttp\Promise\PromiseInterface';
    const PROMISE_HTTP = 'Http\Promise\Promise';
    const PROMISE_AMP = 'Amp\Promise';


    private function promise()
    {
        $resolver = $rejector = null;
        $promise = new Promise(function ($resolve, $reject, $notify) use (&$resolver, &$rejector) {
            $resolver = $resolve;
            $rejector = $reject;
        });
        return [$promise, $resolver, $rejector];
    }

    /**
     * @param mixed ...$parameters
     * @return PromiseInterface
     */
    public function _handleCallback(...$parameters): PromiseInterface
    {
        if (!count($parameters)) {
            throw new ArgumentCountError('No callback specified.');
        }
        $resolver = $rejector = null;
        $promise = new Promise(function ($resolve, $reject, $notify) use (&$resolver, &$rejector) {
            $resolver = $resolve;
            $rejector = $reject;
        });
        $func = array_shift($parameters);
        if (!is_callable($func)) {
            $func = [$func];
            $func[] = array_shift($parameters);
        }
        if (!is_callable($func)) {
            throw new InvalidArgumentException('Valid callable is required.');
        }
        $parameters[] = function ($error, $result) use (&$resolver, &$rejector) {
            if ($error) {
                $rejector($error);
                return;
            }
            $resolver($result);
        };
        call_user_func_array($func, $parameters);
        return $promise;
    }

    /**
     * @param Generator $flow
     * @return PromiseInterface
     */
    private function _handleGenerator(Generator $flow): PromiseInterface
    {

    }

    /**
     * Handle known promise interfaces
     *
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface $knownPromise
     * @return PromiseInterface
     */
    public function _handlePromise($knownPromise): PromiseInterface
    {
        $resolver = $rejector = null;
        $promise = new Promise(function ($resolve, $reject, $notify) use (&$resolver, &$rejector) {
            $resolver = $resolve;
            $rejector = $reject;
        });
        $knownPromise->then($resolver, $rejector);
        return $promise;
    }

}