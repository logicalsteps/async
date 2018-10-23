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


    public function _handleCallback(callable $callable, ...$parameters): PromiseInterface
    {
        list($promise, $resolver, $rejector) = $this->promise();
        $parameters[] = function ($error, $result) use (&$resolver, &$rejector) {
            if ($error) {
                $rejector($error);
                return;
            }
            $resolver($result);
        };
        call_user_func_array($callable, $parameters);
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
        list($promise, $resolver, $rejector) = $this->promise();
        $knownPromise->then($resolver, $rejector);
        return $promise;
    }

}