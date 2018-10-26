<?php

namespace LogicalSteps\Async;


use Closure;
use Generator;
use Psr\Log\LoggerInterface;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionFunctionAbstract;
use ReflectionGenerator;
use ReflectionMethod;

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

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->logger = $logger;
        }
    }

    public function await($value): PromiseInterface
    {
        if ($this->logger) {
            $this->logger->info('start');
        }
        return $this->_handle($value, -1)->then(
            function ($result) {
                if ($this->logger) {
                    $this->logger->info('end');
                }
            },
            function ($error) {
                if ($this->logger) {
                    $this->logger->error('error: ' . (string)$error);
                }
            });
    }

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
            return $this->_handleCallback($func, $arguments, $depth);
        } elseif ($value instanceof Generator) {
            return $this->_handleGenerator($value, $depth);
        } elseif ($implements = array_intersect(class_implements($value), Async2::$knownPromises)) {
            return $this->_handlePromise($value, array_shift($implements), $depth);
        } else {
            return new FulfilledPromise($value);
        }
    }


    public function _handleCallback(callable $callable, array $parameters, int $depth = 0): PromiseInterface
    {
        $this->logCallback($callable, $parameters, $depth);
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
        $this->logGenerator($flow, $depth);
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
        $nextPromise = $this->_handle($value, 1 + $depth);
        $nextPromise->then($next, $rejector);
        return $promise;
    }

    /**
     * Handle known promise interfaces
     *
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface|\Amp\Promise|\Http\Promise\Promise $knownPromise
     * @param string $interface
     * @param int $depth
     * @return PromiseInterface
     */
    public function _handlePromise($knownPromise, string $interface, int $depth = 0): PromiseInterface
    {
        $this->logPromise($knownPromise, $interface, $depth);
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

    private function logCallback(callable $callable, array $parameters, int $depth = 0)
    {
        if ($depth < 0 || !$this->logger) {
            return;
        }
        if (is_array($callable)) {
            $name = $callable[0];
            if (is_object($name)) {
                $name = '$' . lcfirst(get_class($name)) . '->' . $callable[1];
            } else {
                $name .= '::' . $callable[1];
            }

        } else {
            if (is_string($callable)) {
                $name = $callable;
            } elseif ($callable instanceof Closure) {
                $name = '$closure';
            } else {
                $name = '$callable';
            }
        }
        $this->logger->info('await ' . $name . $this->format($parameters), compact('depth'));

    }

    private function logPromise($promise, string $interface, int $depth)
    {
        if ($depth < 0 || !$this->logger) {
            return;
        }
        switch ($interface) {
            case static::PROMISE_REACT:
                $type = 'react';
                break;
            case static::PROMISE_GUZZLE:
                $type = 'guzzle';
                break;
            case static::PROMISE_HTTP:
                $type = 'httplug';
                break;
            case static::PROMISE_AMP:
                $type = 'amp';
                break;
        }
        $this->logger->info('await $' . $type . 'Promise;', compact('depth'));

    }

    private function logGenerator(Generator $generator, int $depth = 0)
    {
        if ($depth < 0 || !$generator->valid() || !$this->logger) {
            return;
        }
        $info = new ReflectionGenerator($generator);
        $this->logReflectionFunction($info->getFunction(), $depth);
    }

    private function format($parameters)
    {
        return '(' . substr(json_encode($parameters), 1, -1) . ');';
    }

    private function logReflectionFunction(ReflectionFunctionAbstract $function, int $depth = 0)
    {
        if ($function instanceof ReflectionMethod) {
            $name = $function->getDeclaringClass()->getShortName();
            if ($function->isStatic()) {
                $name .= '::' . $function->name;
            } else {
                $name = '$' . lcfirst($name) . '->' . $function->name;
            }
        } elseif ($function->isClosure()) {
            $name = '$closure';
        } else {
            $name = $function->name;
        }
        $args = [];
        foreach ($function->getParameters() as $parameter) {
            $args[] = '$' . $parameter->name;
        }
        $this->logger->info('await ' . $name . '(' . implode(', ', $args) . ');', compact('depth'));
    }
}