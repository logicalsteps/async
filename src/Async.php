<?php

namespace LogicalSteps\Async;


use Closure;
use Generator;
use Psr\Log\LoggerInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionFunctionAbstract;
use ReflectionGenerator;
use ReflectionMethod;

/**
 * @method static setLogger(EchoLogger $param)
 * @method static await($value)
 * @method setLogger(EchoLogger $param)
 * @method await($value)
 */
class Async
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
    /**
     * @var bool
     */
    public $waitForGuzzleAndHttplug = true;

    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->logger = $logger;
        }
    }

    public function __call($name, $arguments)
    {
        switch ($name) {
            case 'await':
            case 'setLogger':
                return call_user_func_array([$this, "_$name"], $arguments);
        }
    }

    public static function __callStatic($name, $arguments)
    {
        static $instance;
        if (!$instance) {
            $instance = new static();
        }
        return $instance->__call($name, $arguments);
    }

    protected function _await($value): PromiseInterface
    {
        if ($this->logger) {
            $this->logger->info('start');
        }
        list($promise, $resolver, $rejector) = $this->promise();
        $callback = function ($error, $result) use ($resolver, $rejector) {
            if ($error) {
                if ($this->logger) {
                    $this->logger->info('end');
                }
                $rejector($error);
                return;
            }
            if ($this->logger) {
                $this->logger->info('end');
            }
            $resolver($result);
        };
        $this->_handle($value, $callback, -1);
        return $promise;
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    protected function _setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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

    protected function _handle($value, callable $callback, int $depth = 0)
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
            $this->_handleCallback($func, $arguments, $callback, $depth);
        } elseif ($value instanceof Generator) {
            $this->_handleGenerator($value, $callback, 1 + $depth);
        } elseif ($implements = array_intersect(class_implements($value), Async::$knownPromises)) {
            $this->_handlePromise($value, array_shift($implements), $callback, $depth);
        } else {
            $callback(null, $value);
        }
    }


    protected function _handleCallback(callable $callable, array $parameters, callable $callback, int $depth = 0)
    {
        $this->logCallback($callable, $parameters, $depth);
        $parameters[] = $callback;
        call_user_func_array($callable, $parameters);
    }

    protected function _handleGenerator(Generator $flow, callable $callback, int $depth = 0)
    {
        $this->logGenerator($flow, $depth - 1);

        if (!$flow->valid()) {
            return $callback(null, $flow->getReturn());
        }
        $value = $flow->current();
        $next = function ($error, $result) use ($flow, $callback, $depth) {
            if ($error) {
                return $callback($error);
            }
            $flow->send($result);
            $this->_handleGenerator($flow, $callback, $depth);
        };
        $this->_handle($value, $next, $depth);
    }

    /**
     * Handle known promise interfaces
     *
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface|\Amp\Promise|\Http\Promise\Promise $knownPromise
     * @param string $interface
     * @param int $depth
     * @return PromiseInterface
     */
    protected function _handlePromise($knownPromise, string $interface, callable $callback, int $depth = 0)
    {
        $this->logPromise($knownPromise, $interface, $depth);
        $resolver = function ($result) use ($callback) {
            $callback(null, $result);
        };
        $rejector = function ($error) use ($callback) {
            $callback($error);
        };
        switch ($interface) {
            case static::PROMISE_REACT:
                $knownPromise->then($resolver, $rejector);
                break;
            case static::PROMISE_GUZZLE:
            case static::PROMISE_HTTP:
                $knownPromise->then($resolver, $rejector);
                if ($this->waitForGuzzleAndHttplug) {
                    $knownPromise->wait(false);
                }
                break;
            case static::PROMISE_AMP:
                $knownPromise->onResolve(
                    function ($error, $result) use ($resolver, $rejector) {
                        $error ? $rejector($error) : $resolver($result);
                    });
                break;
        }
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