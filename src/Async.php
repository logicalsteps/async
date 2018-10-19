<?php

namespace LogicalSteps\Async;

use Closure;
use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use function React\Promise\all;
use React\Promise\Deferred;
use React\Promise\Promise;
use ReflectionFunctionAbstract;
use ReflectionGenerator;
use ReflectionMethod;
use Throwable;

class Async implements LoggerAwareInterface
{
    const PROMISE_REACT = 'React\Promise\PromiseInterface';
    const PROMISE_GUZZLE = 'GuzzleHttp\Promise\PromiseInterface';
    const PROMISE_HTTP = 'Http\Promise\Promise';
    const PROMISE_AMP = 'Amp\Promise';
    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var callable that maps to _execute or _executeReactLoop ...
     */
    protected $exec;

    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger) {
            $this->logger = $logger;
        }
        $this->exec = [$this, '_execute'];
    }

    public function await(Generator $flow): Promise
    {
        if ($this->logger) {
            $this->logger->info('start');
        }
        $deferred = new Deferred();
        ($this->exec)($flow, function ($error, $result) use ($deferred) {
            if ($this->logger) {
                $this->logger->info('end');
            }
            if ($error) {
                return $deferred->reject($error);
            }
            $deferred->resolve($result);
        });

        return $deferred->promise();
    }

    public function awaitAll(Generator ...$flows): Promise
    {
        return all(array_map([$this, 'await'], $flows));
    }

    private function _execute(Generator $flow, callable $callback = null, int $depth = 0)
    {
        $this->_run($flow, $callback, $depth);
    }

    private function _executeReactLoop(Generator $flow, callable $callback = null, int $depth = 0)
    {
        $this->loop->futureTick(function () use ($flow, $callback, $depth) {
            $this->_run($flow, $callback, $depth);
        });
    }

    private function _executeAmpLoop(Generator $flow, callable $callback = null, int $depth = 0)
    {

        ('\Amp\Loop::defer')(function () use ($flow, $callback, $depth) {
            $this->_run($flow, $callback, $depth);
        });
    }


    private function _run(Generator $flow, callable $callback = null, int $depth = 0)
    {
        try {
            if (!$flow->valid()) {
                $value = $flow->getReturn();
                if ($value instanceof Generator) {
                    $this->logGenerator($value, $depth);
                    ($this->exec)($value, $callback, $depth + 1);
                } elseif (is_callable($callback)) {
                    $callback(null, $value);
                }

                return;
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
                $this->logCallable($func, $args, $depth);
                $args[] = function ($error, $result) use ($flow, $callback, $depth) {
                    if ($error) {
                        if ($this->logger) {
                            $this->logger->error((string)$error, compact('depth'));
                        }
                        if (is_callable($callback)) {
                            $callback($error);
                        }

                        return;
                    }
                    $flow->send($result);
                    ($this->exec)($flow, $callback, $depth);
                };
                call_user_func_array($func, $args);
            } elseif ($value instanceof Generator) {
                $this->logGenerator($value, $depth);
                ($this->exec)($value, function ($error, $result) use ($flow, $callback, $depth) {
                    if ($error) {
                        if ($this->logger) {
                            $this->logger->error((string)$error);
                        }
                        if (is_callable($callback)) {
                            $callback($error);
                        }

                        return;
                    }
                    $flow->send($result);
                    ($this->exec)($flow, $callback, $depth);
                }, $depth + 1);
            } elseif (is_a($value, static::PROMISE_REACT)) {
                $this->handlePromise($flow, $callback, $depth, $value, 'react');
            } elseif (is_a($value, static::PROMISE_GUZZLE)) {
                $this->handlePromise($flow, $callback, $depth, $value, 'guzzle');
                $value->wait(false);
            } elseif (is_a($value, static::PROMISE_HTTP)) {
                $this->handlePromise($flow, $callback, $depth, $value, 'httplug');
                $value->wait(false);
            } elseif (is_a($value, static::PROMISE_AMP)) {
                if ($this->logger) {
                    $this->logger->info('await $ampPromise;');
                }
                $value->onResolve(
                    function ($error, $result) use ($flow, $callback, $depth) {
                        if ($error) {
                            if ($this->logger) {
                                $this->logger->error((string)$error);
                            }
                            if (is_callable($callback)) {
                                $callback($error);
                            }

                            return;
                        }
                        $flow->send($result);
                        ($this->exec)($flow, $callback, $depth);
                    }
                );
            } else {
                $flow->send($value);
                ($this->exec)($flow, $callback, $depth);
            }

        } catch
        (Throwable $t) {
            $flow->throw($t);
            ($this->exec)($flow);
        }
    }

    /**
     * Handle known promise interfaces
     *
     * @param Generator $flow
     * @param callable $callback
     * @param int $depth
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface $value
     * @param string $type
     */
    private function handlePromise(Generator $flow, callable $callback, int $depth, $value, string $type)
    {
        if ($this->logger) {
            $this->logger->info('await $' . $type . 'Promise;');
        }
        $value->then(
            function ($result) use ($flow, $callback, $depth) {
                $flow->send($result);
                ($this->exec)($flow, $callback, $depth);
            },
            function ($error) use ($callback, $depth) {
                if ($this->logger) {
                    $this->logger->error((string)$error, compact('depth'));
                }
                if (is_callable($callback)) {
                    $callback($error);
                }
            }
        );
    }

    private function logCallable(callable $callable, array $arguments, int $depth = 0)
    {
        if (!$this->logger) {
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
        $this->logger->info('await ' . $name . $this->format($arguments), compact('depth'));

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

    private function logGenerator(Generator $generator, int $depth = 0)
    {
        if (!$generator->valid() || !$this->logger) {
            return;
        }
        $info = new ReflectionGenerator($generator);
        $this->logReflectionFunction($info->getFunction(), $depth);
    }

    private function format($parameters)
    {
        return '(' . substr(json_encode($parameters), 1, -1) . ');';
    }

    /**
     * A method used to test whether this class is autoloaded.
     *
     * @return bool
     *
     * @see \LogicalSteps\Async\Test\DummyTest
     */
    public function autoloaded()
    {
        return true;
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param LoopInterface $loop
     */
    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->exec = [$this, '_executeReactLoop'];
    }

    public function useAmpLoop()
    {
        $this->exec = [$this, '_executeAmpLoop'];
    }
}
