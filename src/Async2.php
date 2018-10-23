<?php

namespace LogicalSteps\Async;


use Generator;
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


    private function _handleGenerator(Generator $flow): PromiseInterface
    {
        list($promise, $resolver, $rejector) = $this->promise();

        if (!$flow->valid()) {
            $resolver($flow->getReturn());

            return $promise;
        }
        $value = $flow->current();
        $args = [];
        $func = [];
        if (is_array($value) && count($value) > 1) {
            $func[] = array_shift($value);
            if (is_callable($func[0])) {
                $func = $func[0];
            } else {
                $func[] = array_shift($value);
            }
            $args = $value;
        } else {
            $func = $value;
        }
        if (is_callable($func)) {
            $this->_handleCallback($func, ...$args);
        } elseif ($value instanceof Generator) {
            $this->_handleGenerator($flow);
        } elseif ($implements = array_intersect(class_implements($value), Async2::$knownPromises)) {
            $this->_handlePromise($value, array_shift($implements));
        } else {
            $flow->send($value);
            $this->_handleGenerator($flow);
        }
        return $promise;
    }

    /**
     * Handle known promise interfaces
     *
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface $knownPromise
     * @return PromiseInterface
     */
    public function _handlePromise($knownPromise, string $interface): PromiseInterface
    {
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