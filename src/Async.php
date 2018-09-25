<?php

namespace LogicalSteps\Async;

use Generator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Async implements LoggerAwareInterface
{
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
                                $name = '(new '.get_class($name) . ')->' . $func[1];
                            } else {
                                $name .= '::' . $func[1];
                            }

                        } else {
                            $name = (string)$func;
                        }
                        $this->logger->info('yield ' . $name . $this->format($args));
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
                    $this->execute($value, $callback);
                } elseif (is_callable($callback)) {
                    $callback($value);
                }
            }
        } catch (Throwable $t) {
            $flow->throw($t);
            $this->execute($flow);
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
}
