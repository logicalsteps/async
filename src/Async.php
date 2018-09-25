<?php

namespace LogicalSteps\Async;

use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use ReflectionGenerator;
use Throwable;

class Async implements LoggerAwareInterface
{
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

    public function execute(Generator $flow, callable $callback = null)
    {
        if ($this->loop) {
            $this->loop->futureTick(function () use ($flow, $callback) {
                $this->run($flow, $callback);
            });
        } else {
            $this->run($flow, $callback);
        }
    }

    private function run(Generator $flow, callable $callback = null)
    {
        try {
            try {
                if ($flow->valid()) {
                    $value = $flow->current();
                    $args  = [];
                    $func  = [];
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
                        if ($this->logger) {
                            if (is_array($func)) {
                                $name = $func[0];
                                if (is_object($name)) {
                                    $name = '$' . lcfirst(get_class($name)) . '->' . $func[1];
                                } else {
                                    $name .= '::' . $func[1];
                                }

                            } else {
                                $name = (string)$func;
                            }
                            $this->logger->info('await ' . $name . $this->format($args));
                        }
                        $args[] = function ($error, $result) use ($flow, $callback) {
                            if ($error) {
                                if ($this->logger) {
                                    $this->logger->error((string)$error);
                                }
                                throw $error;
                            }
                            $flow->send($result);
                            $this->execute($flow, $callback);
                        };
                        call_user_func_array($func, $args);
                    } elseif ($value instanceof Generator) {
                        if ($value->valid() && $this->logger) {
                            $info = new ReflectionGenerator($value);
                            $f    = $info->getFunction();
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
                            $this->logger->info('await ' . $name . '(' . implode(', ', $args) . ');');
                        }
                        $this->execute($value, function ($value) use ($flow, $callback) {
                            $flow->send($value);
                            $this->execute($flow, $callback);
                        });
                    } else {
                        $flow->send($value);
                        $this->execute($flow);
                    }
                } else {
                    $value = $flow->getReturn();
                    if ($value instanceof Generator) {
                        if ($value->valid() && $this->logger) {
                            $info = new ReflectionGenerator($value);
                            $f    = $info->getFunction();
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
                            $this->logger->info('await ' . $name . '(' . implode(', ', $args) . ');');
                        }
                        $this->execute($value, $callback);
                    } elseif (is_callable($callback)) {
                        $callback(null, $value);
                    }
                }
            } catch (Throwable $t) {
                $flow->throw($t);
                $this->execute($flow);
            }
        } catch (Throwable $t) {
            if (is_callable($callback)) {
                $callback($t);
            }
        }
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
