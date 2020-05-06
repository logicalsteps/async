<?php

namespace LogicalSteps\Async;


use Closure;
use Generator;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Amp\Loop\Driver;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionGenerator;
use ReflectionMethod;
use ReflectionObject;
use Throwable;
use TypeError;
use function GuzzleHttp\Promise\all as guzzleAll;

/**
 * @method static mixed wait($process) synchronously wait for the completion of an asynchronous process
 * @method mixed wait($process) synchronously wait for the completion of an asynchronous process
 *
 * @method static PromiseInterface await($process) await for the completion of an asynchronous process
 * @method PromiseInterface await($process) await for the completion of an asynchronous process
 *
 * @method static void await($process, callable $callback) await for the completion of an asynchronous process
 * @method void await($process, callable $callback) await for the completion of an asynchronous process
 *
 * @method static PromiseInterface awaitAll(array $processes) concurrently await for multiple processes
 * @method PromiseInterface awaitAll(array $processes) concurrently await for multiple processes
 *
 * @method static void awaitAll(array $processes, callable $callback) concurrently await for multiple processes
 * @method void awaitAll(array $processes, callable $callback) concurrently await for multiple processes
 *
 * @method static setLogger(LoggerInterface|null $logger)
 * @method setLogger(LoggerInterface|null $logger)
 *
 * @method static setEventLoop(LoopInterface|Driver|null $loop)
 * @method setEventLoop(LoopInterface|Driver|null $loop)
 */
class Async
{
    const PROMISE_REACT = 'React\Promise\PromiseInterface';
    const PROMISE_AMP = 'Amp\Promise';
    const PROMISE_GUZZLE = 'GuzzleHttp\Promise\PromiseInterface';
    const PROMISE_HTTP = 'Http\Promise\Promise';

    /** @var string action to return a promise instead of awaiting the response of the process. */
    const promise = 'promise';
    /** @var string action to run current process side by side with the remainder of the process. */
    const parallel = 'parallel';
    /** @var string action to await for all parallel processes previously to finish. */
    const all = 'all';
    /** @var string action to await for current  processes to finish. this is the default action. */
    const await = 'await';
    /** @var string action to run current process after finished executing the function. */
    const later = 'later';

    const ACTIONS = [self::await, self::parallel, self::all, self::promise, self::later];

    public static $knownPromises = [
        self::PROMISE_REACT,
        self::PROMISE_AMP,
        self::PROMISE_GUZZLE,
        self::PROMISE_HTTP,
    ];

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var LoopInterface|Driver|null;
     */
    protected $loop;


    /**
     * @var bool
     */
    public $waitForGuzzleAndHttplug = true;

    /**
     * @var bool
     */
    protected $parallelGuzzleLoading = false;

    protected $guzzlePromises = [];

    /**
     * Async constructor.
     * @param LoggerInterface|null $logger
     * @param LoopInterface|Driver|null $eventLoop optional. required when using reactphp or amphp
     */
    public function __construct(?LoggerInterface $logger = null, $eventLoop = null)
    {
        $this->logger = $logger;
        $this->loop = $eventLoop;
    }

    public function __call($name, $arguments)
    {
        $value = null;
        $method = "_$name";
        switch ($name) {
            case 'await':
            case 'awaitAll':
                if ($this->logger) {
                    $this->logger->info('start');
                }
                if (1 == count($arguments)) {
                    //return promise
                    list($value, $resolver, $rejector) = $this->makePromise();
                    $callback = function ($error = null, $result = null) use ($resolver, $rejector) {
                        if ($this->logger) {
                            $this->logger->info('end');
                        }
                        if ($error) {
                            return $rejector($error);
                        }
                        return $resolver($result);
                    };
                    $arguments[1] = $callback;
                } elseif ($this->logger) {
                    $c = $arguments[1];
                    $callback = function ($error, $result = null) use ($c) {
                        $this->logger->info('end');
                        $c($error, $result);
                    };
                    $arguments[1] = $callback;
                }
                if ('await' == $name) {
                    $arguments[2] = -1;//depth
                    $method = '_handle';
                }
                break;
            case 'setLogger':
            case 'setEventLoop':
                break;
            case 'wait':
                return call_user_func_array([$this, $method], $arguments);
            default:
                return null;
        }
        call_user_func_array([$this, $method], $arguments);
        return $value;
    }

    public static function __callStatic($name, $arguments)
    {
        if (empty($arguments) && in_array($name, self::ACTIONS)) {
            return $name;
        }
        static $instance;
        if (!$instance) {
            $instance = new static();
        }
        return $instance->__call($name, $arguments);
    }

    /**
     * Throws specified or subclasses of specified exception inside the generator class so that it can be handled.
     *
     * @param string $throwable
     * @return string command
     *
     * @throws TypeError when given value is not a valid exception
     */
    public static function throw(string $throwable): string
    {
        if (is_a($throwable, Throwable::class, true)) {
            return __FUNCTION__ . ':' . $throwable;
        }
        throw new TypeError('Invalid value for throwable, it must extend Throwable class');
    }

    protected function _awaitAll(array $processes, callable $callback): void
    {
        $this->parallelGuzzleLoading = true;
        $results = [];
        $failed = false;
        foreach ($processes as $key => $process) {
            if ($failed)
                break;
            $c = function ($error = null, $result = null) use ($key, &$results, $processes, $callback, &$failed) {
                if ($failed)
                    return;
                if ($error) {
                    $failed = true;
                    $callback($error);
                    return;
                }
                $results[$key] = $result;
                if (count($results) == count($processes)) {
                    $callback(null, $results);
                }
            };
            $this->_handle($process, $c, -1);
        }
        if (!empty($this->guzzlePromises)) {
            guzzleAll($this->guzzlePromises)->wait(false);
            $this->guzzlePromises = [];
        }
        $this->parallelGuzzleLoading = false;
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface|null $logger
     *
     * @return void
     */
    protected function _setLogger(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoopInterface|Driver|null $loop
     *
     * @return void
     */
    protected function _setEventLoop($loop)
    {
        $this->loop = $loop;
    }

    private function makePromise()
    {
        $resolver = $rejector = null;
        $promise = new Promise(function ($resolve, $reject, $notify) use (&$resolver, &$rejector) {
            $resolver = $resolve;
            $rejector = $reject;
        });
        return [$promise, $resolver, $rejector];
    }

    protected function _wait($process)
    {
        if ($this->logger) {
            $this->logger->info('start');
        }
        $waiting = true;
        $result = null;
        $exception = null;
        $isRejected = false;

        $callback = function ($error = null, $r = null) use (&$result, &$waiting, &$isRejected, &$exception) {
            $waiting = false;
            if ($this->loop) {
                $this->loop->stop();
            }
            if ($error) {
                $isRejected = true;
                $exception = $error;
                return;
            }
            $result = $r;
        };
        $this->_handle($process, $callback, -1);
        while ($waiting) {
            if ($this->loop) {
                $this->loop->run();
            }
        }
        if ($this->logger) {
            $this->logger->info('end');
        }
        if ($isRejected) {
            if (!$exception instanceof \Exception) {
                $exception = new \UnexpectedValueException(
                    'process failed with ' . (is_object($exception) ? get_class($exception) : gettype($exception))
                );
            }
            throw $exception;
        }

        return $result;
    }

    protected function _handle($process, callable $callback, int $depth = 0): void
    {
        $arguments = [];
        $func = [];
        if (is_array($process) && count($process) > 1) {
            $copy = $process;
            $func[] = array_shift($copy);
            if (is_callable($func[0])) {
                $func = $func[0];
            } else {
                $func[] = array_shift($copy);
            }
            $arguments = $copy;
        } else {
            $func = $process;
        }
        if (is_callable($func)) {
            $this->_handleCallback($func, $arguments, $callback, $depth);
        } elseif ($process instanceof Generator) {
            $this->_handleGenerator($process, $callback, 1 + $depth);
        } elseif (is_object($process) && $implements = array_intersect(class_implements($process),
                Async::$knownPromises)) {
            $this->_handlePromise($process, array_shift($implements), $callback, $depth);
        } else {
            $callback(null, $process);
        }
    }


    protected function _handleCallback(callable $callable, array $parameters, callable $callback, int $depth = 0)
    {
        $this->logCallback($callable, $parameters, $depth);
        try {
            if (is_array($callable)) {
                $rf = new ReflectionMethod($callable[0], $callable[1]);
            } elseif (is_string($callable)) {
                $rf = new ReflectionFunction($callable);
            } elseif (is_a($callable, 'Closure') || is_callable($callable, '__invoke')) {
                $ro = new ReflectionObject($callable);
                $rf = $ro->getMethod('__invoke');
            }
            $current = count($parameters);
            $total = $rf->getNumberOfParameters();
            $ps = $rf->getParameters();
            if ($current + 1 < $total) {
                for ($i = $current; $i < $total - 1; $i++) {
                    $parameters[$i] = $ps[$i]->isDefaultValueAvailable() ? $ps[$i]->getDefaultValue() : null;
                }
            }
        } catch (ReflectionException $e) {
            //ignore
        }
        $parameters[] = $callback;
        call_user_func_array($callable, $parameters);
    }

    protected function _handleGenerator(Generator $flow, callable $callback, int $depth = 0)
    {
        $this->logGenerator($flow, $depth - 1);
        try {
            if (!$flow->valid()) {
                $callback(null, $flow->getReturn());
                if (!empty($flow->later)) {
                    $this->_awaitAll($flow->later, function ($error = null, $results = null) {
                    });
                    unset($flow->later);
                }
                return;
            }
            $value = $flow->current();
            $actions = $this->parse($flow->key() ?: Async::await);
            $next = function ($error = null, $result = null) use ($flow, $actions, $callback, $depth) {
                $value = $error ?: $result;
                if ($value instanceof Throwable) {
                    if (isset($actions['throw']) && is_a($value, $actions['throw'])) {
                        /** @scrutinizer ignore-call */
                        $flow->throw($value);
                        $this->_handleGenerator($flow, $callback, $depth);
                        return;
                    }
                    $callback($value, null);
                    return;
                }
                $flow->send($value);
                $this->_handleGenerator($flow, $callback, $depth);
            };
            if (key_exists(self::later, $actions)) {
                if (!isset($flow->later)) {
                    $flow->later = [];
                }
                if ($this->logger) {
                    $this->logger->info('later task scheduled', compact('depth'));
                }
                $flow->later[] = $value;
                return $next(null, $value);
            }
            if (key_exists(self::parallel, $actions)) {
                if (!isset($flow->parallel)) {
                    $flow->parallel = [];
                }
                $flow->parallel[] = $value;
                if (!isset($this->action)) {
                    $this->action = [];
                }
                $this->action[] = self::parallel;
                return $next(null, $value);
            }
            if (key_exists(self::all, $actions)) {
                $tasks = Async::parallel === $value && isset($flow->parallel) ? $flow->parallel : $value;
                unset($flow->parallel);
                if (is_array($tasks) && count($tasks)) {
                    if ($this->logger) {
                        $this->logger->info(
                            sprintf("all {%d} tasks awaited.", count($tasks)),
                            compact('depth')
                        );
                    }
                    return $this->_awaitAll($tasks, $next);
                }
                return $next(null, []);
            }
            $this->_handle($value, $next, $depth);
        } catch (Throwable $throwable) {
            $callback($throwable, null);
        }
    }

    /**
     * Handle known promise interfaces
     *
     * @param \React\Promise\PromiseInterface|\GuzzleHttp\Promise\PromiseInterface|\Amp\Promise|\Http\Promise\Promise $knownPromise
     * @param string $interface
     * @param callable $callback
     * @param int $depth
     * @return void
     */
    protected function _handlePromise($knownPromise, string $interface, callable $callback, int $depth = 0)
    {
        $this->logPromise($knownPromise, $interface, $depth);
        $resolver = function ($result) use ($callback) {
            $callback(null, $result);
        };
        $rejector = function ($error) use ($callback) {
            $callback($error, null);
        };
        try {
            switch ($interface) {
                case static::PROMISE_REACT:
                    $knownPromise->then($resolver, $rejector);
                    break;
                case static::PROMISE_GUZZLE:
                    $knownPromise->then($resolver, $rejector);
                    if ($this->waitForGuzzleAndHttplug) {
                        if ($this->parallelGuzzleLoading) {
                            $this->guzzlePromises[] = $knownPromise;
                        } else {
                            $knownPromise->wait(false);
                        }
                    }
                    break;
                case static::PROMISE_HTTP:
                    $knownPromise->then($resolver, $rejector);
                    if ($this->waitForGuzzleAndHttplug) {
                        $knownPromise->wait(false);
                    }
                    break;
                case static::PROMISE_AMP:
                    $knownPromise->onResolve(
                        function ($error = null, $result = null) use ($resolver, $rejector) {
                            $error ? $rejector($error) : $resolver($result);
                        });
                    break;
            }
        } catch (\Exception $e) {
            $rejector($e);
        }
    }

    private function handleCommands(Generator $flow, &$value, callable $callback, int $depth): bool
    {
        $commands = $this->parse($flow->key());
        if ($value instanceof Throwable) {
            if (isset($commands['throw']) && is_a($value, $commands['throw'])) {
                $flow->throw($value);
                $this->_handleGenerator($flow, $callback, $depth);
                return true; //stop
            }
            $callback($value, null);
            return true; //stop
        }
        if (isset($commands[self::parallel])) {
            if (!isset($flow->parallel)) {
                $flow->parallel = [];
            }
            $flow->parallel [] = $value;
            return false; //continue
        }

        if (isset($commands[self::all])) {
            if (!isset($flow->parallel)) {
                $callback(null, []);
                return true; //stop
            }
            $this->_awaitAll(
                $flow->parallel,
                function ($error = null, $all = null) use ($flow, $callback, $depth) {
                    if ($error) {
                        $callback($error, false);
                        return;
                    }
                    $flow->send($all);
                    $this->_handleGenerator($flow, $callback, $depth);
                }
            );
            return true; //stop
        }

        return false; //continue
    }

    private function parse(string $command): array
    {
        $arr = [];
        if (strlen($command)) {
            parse_str(str_replace(['|', ':'], ['&', '='], $command), $arr);
        }
        return $arr;
    }

    private function action()
    {
        if (!empty($this->action)) {
            return array_shift($this->action);
        }
        return self::await;
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
        $this->logger->info(
            sprintf("%s %s%s", $this->action(), $name, $this->format($parameters)),
            compact('depth')
        );
    }

    private function logPromise(/** @scrutinizer ignore-unused */ $promise, string $interface, int $depth)
    {
        if ($depth < 0 || !$this->logger) {
            return;
        }
        $type = 'unknown';
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
        $this->logger->info(
            sprintf("%s \$%sPromise;", $this->action(), $type),
            compact('depth')
        );
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
        $this->logger->info(
            sprintf("%s %s(%s);", $this->action(), $name, implode(', ', $args)),
            compact('depth')
        );
    }
}
