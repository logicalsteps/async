<?php

namespace LogicalSteps\Async;


use Closure;
use Generator;
use Psr\Log\LoggerInterface;
use TypeError;
use function Clue\StreamFilter\fun;
use function React\Promise\all;
use function GuzzleHttp\Promise\all as guzzleAll;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionGenerator;
use ReflectionMethod;
use ReflectionObject;
use Throwable;

/**
 * @method static PromiseInterface await($process) await for the completion of an asynchronous process
 * @method PromiseInterface await($process) await for the completion of an asynchronous process
 *
 * @method static PromiseInterface awaitAll(array $processes) concurrently await for multiple processes
 * @method PromiseInterface awaitAll(array $processes) concurrently await for multiple processes
 *
 * @method static setLogger(LoggerInterface $param)
 * @method setLogger(LoggerInterface $param)
 */
class Async
{
    const PROMISE_REACT = 'React\Promise\PromiseInterface';
    const PROMISE_AMP = 'Amp\Promise';
    const PROMISE_GUZZLE = 'GuzzleHttp\Promise\PromiseInterface';
    const PROMISE_HTTP = 'Http\Promise\Promise';

    const promise = 'promise';
    const parallel = 'parallel';
    const all = 'all';
    const await = 'await';

    const ACTIONS = [self::await, self::parallel, self::all, self::promise];

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

    /**
     * @var bool
     */
    protected $parallelGuzzleLoading = false;

    protected $guzzlePromises = [];

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
            case 'awaitAll':
            case 'setLogger':
                return call_user_func_array([$this, "_$name"], $arguments);
        }
        return null;
    }

    public static function __callStatic($name, $arguments)
    {
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

    /**
     * Run this side by side with the remainder of the process
     *
     * @return string
     */
    public static function parallel(): string
    {
        return __FUNCTION__;
    }

    /**
     * Await for all parallel processes previously to finish
     *
     * @return string
     */
    public static function all(): string
    {
        return __FUNCTION__;
    }

    /**
     * Return a promise instead of awaiting the response of the process
     *
     * @return string
     */
    public static function promise(): string
    {
        return __FUNCTION__;
    }

    public function _awaitAll(array $processes): PromiseInterface
    {
        $this->parallelGuzzleLoading = true;
        $promise = all(array_map([$this, '_await'], $processes));
        if (!empty($this->guzzlePromises)) {
            guzzleAll($this->guzzlePromises)->wait(false);
            $this->guzzlePromises = [];
        }
        return $promise;
    }

    protected function _await($process): PromiseInterface
    {
        if ($this->logger) {
            $this->logger->info('start');
        }
        list($promise, $resolver, $rejector) = $this->makePromise();
        $callback = function ($error, $result = null) use ($resolver, $rejector) {
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
        $this->_handle($process, $callback, -1);
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

    private function makePromise()
    {
        $resolver = $rejector = null;
        $promise = new Promise(function ($resolve, $reject, $notify) use (&$resolver, &$rejector) {
            $resolver = $resolve;
            $rejector = $reject;
        });
        return [$promise, $resolver, $rejector];
    }

    protected function _handle($process, callable $callback = null, int $depth = 0)
    {
        $promise = null;
        if (!$callback) {
            list($promise, $resolver, $rejector) = $this->makePromise();
            $callback = function ($error, $result) use ($resolver, $rejector) {
                if ($error) {
                    return $rejector($error);
                }
                $resolver($result);
            };
        }
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
        return $promise;
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
                return;
            }
            $value = $flow->current();
            $actions = $this->parse($flow->key() ?: Async::await);
            $next = function ($error, $result) use ($flow, $actions, $callback, $depth) {
                $value = $error ?: $result;
                if ($value instanceof Throwable) {
                    if (isset($actions['throw']) && is_a($value, $actions['throw'])) {
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
                if (is_array($tasks) && count($tasks)) {
                    $this->logger->info(
                        sprintf("all {%d} tasks awaited.", count($tasks)),
                        compact('depth')
                    );
                    return all(array_map([$this, '_handle'], $tasks))->then(
                        function ($result) use ($next) {
                            $next(null, $result);
                        },
                        function ($error) use ($next) {
                            $next($error, null);
                        }
                    );
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
     * @throws \Exception
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
                    function ($error, $result) use ($resolver, $rejector) {
                        $error ? $rejector($error) : $resolver($result);
                    });
                break;
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
            $this->_awaitAll($flow->parallel)->then(
                function (array $all) use ($flow, $callback, $depth) {
                    $flow->send($all);
                    $this->_handleGenerator($flow, $callback, $depth);
                },
                function ($err) use ($callback) {
                    $callback($err, false);
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
            //$arr = array_map('str_getcsv', $arr);
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
