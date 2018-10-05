<?php

namespace LogicalSteps\Async;

use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use ReflectionGenerator;
use Throwable;

class Async implements LoggerAwareInterface
{
    const PROMISE_REACT = 'React\Promise\PromiseInterface';
    const PROMISE_GUZZLE = 'GuzzleHttp\Promise\PromiseInterface';
    /**
     * @var LoopInterface
     */
    protected $loop;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = new EchoLogger();
    }

    public function promise(Generator $flow): Promise
    {
        $deferred = new Deferred();
        $this->execute($flow, function ($error, $result) use ($deferred) {
            if ($error) {
                return $deferred->reject($error);
            }
            $deferred->resolve($result);
        });

        return $deferred->promise();
    }

    public function execute(Generator $flow, callable $callback = null)
    {
        $this->logger->info('start');
        $wrapped_callback = function ($error, $result) use ($callback) {
            if ($this->logger) {
                $this->logger->info('end');
            }
            if (is_callable($callback)) {
                $callback($error, $result);
            }
        };
        $this->_execute($flow, $wrapped_callback);

    }

    private function _execute(Generator $flow, callable $callback = null, int $depth = 0)
    {
        if ($this->loop) {
            $this->loop->futureTick(function () use ($flow, $callback, $depth) {
                $this->_run($flow, $callback, $depth);
            });
        } else {
            $this->_run($flow, $callback, $depth);
        }
    }

    private function _run(Generator $flow, callable $callback = null, int $depth = 0)
    {
        try {
            if (!$flow->valid()) {
                $value = $flow->getReturn();
                if ($value instanceof Generator) {
                    $this->logGenerator($value, $depth);
                    $this->_execute($value, $callback, $depth + 1);
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
                    $this->_execute($flow, $callback, $depth);
                };
                call_user_func_array($func, $args);
            } elseif (is_a($value, static::PROMISE_REACT)) {
                $this->handlePromise($flow, $callback, $depth, $value);
            } elseif (is_a($value, static::PROMISE_GUZZLE)) {
                $this->handlePromise($flow, $callback, $depth, $value);
                $value->wait();
            } elseif ($value instanceof Generator) {
                $this->logGenerator($value, $depth);
                $this->_execute($value, function ($error, $result) use ($flow, $callback, $depth) {
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
                    $this->_execute($flow, $callback, $depth);
                }, $depth + 1);
            } else {
                $flow->send($value);
                $this->_execute($flow, $callback, $depth);
            }

        } catch
        (Throwable $t) {
            $flow->throw($t);
            $this->_execute($flow);
        }
    }

    /**
     * Handle known promise interfaces
     *
     * @param Generator $flow
     * @param callable $callback
     * @param int $depth
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface $value
     */
    private function handlePromise(Generator $flow, callable $callback, int $depth, $value)
    {
        if ($this->logger) {
            $this->logger->info('await $promise;');
        }
        $value->then(
            function ($result) use ($flow, $callback, $depth) {
                $flow->send($result);
                $this->_execute($flow, $callback, $depth);
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
            $name = (string)$callable;
        }
        $this->logger->info('await ' . $name . $this->format($arguments), compact('depth'));

    }

    private function logGenerator(Generator $generator, int $depth = 0)
    {
        if (!$generator->valid() || !$this->logger) {
            return;
        }
        $info = new ReflectionGenerator($generator);
        $f = $info->getFunction();
        if ($name = $info->getThis()) {
            if (is_object($name)) {
                $name = '$' . lcfirst(get_class($name)) . '->' . $f->name;
            } else {
                $name .= '::' . $f->name;
            }
        } else {
            $name = $f->name;
        }
        $args = [];
        foreach ($f->getParameters() as $parameter) {
            $args[] = '$' . $parameter->name;
        }
        $this->logger->info('await ' . $name . '(' . implode(', ', $args) . ');', compact('depth'));

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
    }
}
