<?php

namespace LogicalSteps\Async;


use Generator;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class Async2
{
    const PROMISE_REACT = 'React\Promise\PromiseInterface';
    const PROMISE_AMP = 'Amp\Promise';
    const PROMISE_GUZZLE = 'GuzzleHttp\Promise\PromiseInterface';
    const PROMISE_HTTP = 'Http\Promise\Promise';

    public static $knownPromises = [
        self::PROMISE_REACT,
        self::PROMISE_AMP,
        self::PROMISE_GUZZLE,
        self::PROMISE_HTTP
    ];

    private function promise()
    {
        $resolver = $rejector = null;
        $promise = new Promise(function ($resolve, $reject, $notify) use (&$resolver, &$rejector) {
            $resolver = $resolve;
            $rejector = $reject;
        });
        return [$promise, $resolver, $rejector];
    }

    public function _handle($value, int $depth = 0): PromiseInterface
    {
        $arguments = [];
        $func = [];
        if (is_array($value) && count($value) > 1) {
            $func[] = array_shift($value);
            if (is_callable($func[0])) {
                $func = $func[0];
            } else {
                $func[] = array_shift($value);
            }
            $arguments = $value;
        } else {
            $func = $value;
        }
        if (is_callable($func)) {
            return $this->_handleCallback($func, $arguments, $depth+1);
        } elseif ($value instanceof Generator) {
            return $this->_handleGenerator($value, $depth+1);
        } elseif ($implements = array_intersect(class_implements($value), Async2::$knownPromises)) {
            return $this->_handlePromise($value, array_shift($implements), $depth+1);
        } else {
            return new FulfilledPromise($value);
        }
    }


    public function _handleCallback(callable $callable, array $parameters, int $depth = 0): PromiseInterface
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

    public function _handleGenerator(Generator $flow, int $depth = 0): PromiseInterface
    {
        list($promise, $resolver, $rejector) = $this->promise();

        if (!$flow->valid()) {
            $resolver($flow->getReturn());

            return $promise;
        }
        $value = $flow->current();
        $next = function ($result) use ($flow, $resolver, $rejector) {
            $flow->send($result);
            $this->_handleGenerator($flow)->then($resolver, $rejector);
        };
        $nextPromise = $this->_handle($value);
        $nextPromise->then($next, $rejector);
        return $promise;
    }

    /**
     * Handle known promise interfaces
     *
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface|\Amp\Promise|\Http\Promise\Promise $knownPromise
     * @return PromiseInterface
     */
    public function _handlePromise($knownPromise, string $interface, int $depth = 0): PromiseInterface
    {
        if ($knownPromise instanceof PromiseInterface) {
            return $knownPromise;
        }
        list($promise, $resolver, $rejector) = $this->promise();
        switch ($interface) {
            case static::PROMISE_REACT:
                $knownPromise->then($resolver, $rejector);
                break;
            case static::PROMISE_GUZZLE:
            case static::PROMISE_HTTP:
                $knownPromise->then($resolver, $rejector);
                //$knownPromise->wait(false); //TODO: handle waiting elsewhere
                break;
            case static::PROMISE_AMP:
                $knownPromise->onResolve(
                    function ($error, $result) use ($resolver, $rejector) {
                        $error ? $rejector($error) : $resolver($result);
                    });
                break;
        }

        return $promise;
    }
}