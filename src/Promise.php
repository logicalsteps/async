<?php


namespace LogicalSteps\Async;


class Promise
{
    public const PENDING = 'pending';
    public const FULFILLED = 'fulfilled';
    public const REJECTED = 'rejected';

    private $state = self::PENDING;
    private $result;
    private $handlers = [];


    public function __construct(callable $executor)
    {
        $target =& $this;
        $reject = static function ($reason = null) use (&$target) {
            if ($target !== null) {
                $target->reject($reason);
                $target = null;
            }
        };
        try {
            $executor(
                static function ($value = null) use (&$target) {
                    if ($target !== null) {
                        $target->resolve($value);
                        $target = null;
                    }
                },
                $reject
            );
        } catch (\Throwable $exception) {
            $reject($exception);
        }
    }

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): Promise
    {
        return new self(
            function (callable $resolve = null, callable $reject = null) use ($onFulfilled, $onRejected) {
                $_onFulfilled = function ($res) use ($resolve, $reject, $onFulfilled) {
                    try {
                        $resolve($onFulfilled($res));
                    } catch (\Throwable $exception) {
                        $reject($exception);
                    }
                };
                $_onRejected = function ($err) use ($reject, $onRejected) {
                    try {
                        $reject($onRejected($err));
                    } catch (\Throwable $exception) {
                        $reject($exception);
                    }
                };
                if (self::FULFILLED === $this->state) {
                    call_user_func($_onFulfilled, $this->result);
                } elseif (self::REJECTED === $this->state) {
                    call_user_func($_onRejected, $this->result);
                } else {
                    $this->handlers[] = (object)['onFulfilled' => $_onFulfilled, 'onRejected' => $_onRejected];
                }
            }
        );
    }

    public function otherwise(callable $onRejected)
    {
        return $this->then(null, $onRejected);
    }

    public function getState()
    {
        return $this->state;
    }

    public function resolve($value)
    {
        if (self::PENDING !== $this->state) {
            return;
        }
        if ($value === $this) {
            return $this->reject(new TypeError('Can\'t resolve promise with itself'));
        }
        if ($value instanceof Promise) {
            $wrapped = $this->wrapResolveReject();
            try {
                return $value->then([$wrapped, 'resolve'], [$wrapped, 'reject']);
            } catch (\Throwable $exception) {
                $wrapped->reject($exception);
            }
        }
        $this->state = self::FULFILLED;
        $this->result = $value;
        foreach ($this->handlers as $handler) {
            if (is_callable($handler->onFulfilled)) {
                call_user_func($handler->onFulfilled, $this->result);
            }
        }
    }

    private function wrapResolveReject()
    {
        $called = false;
        $resolve = function ($v) use (&$called) {
            if ($called) {
                return;
            }
            $called = true;
            $this->resolve($v);
        };
        $reject = function ($err) use (&$called) {
            if ($called) {
                return;
            }
            $called = true;
            $this->reject($err);
        };
        return (object)compact('resolve', 'reject');
    }

    public function reject($reason)
    {
        if (self::PENDING !== $this->state) {
            return;
        }
        $this->result = $reason;
        $this->state = self::REJECTED;
        foreach ($this->handlers as $handler) {
            if (is_callable($handler->onRejected)) {
                call_user_func($handler->onRejected, $this->result);
            }
        }
    }

    public function cancel()
    {
        if (self::PENDING === $this->state) {
            $this->reject(new \Exception('Promise has been cancelled'));
        }
    }
}
