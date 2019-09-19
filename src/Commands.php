<?php


namespace LogicalSteps\Async;


use Error;
use Exception;
use Throwable;

class Commands
{
    public static $commands = [
        'parallel' => [],
        'await' => [],
        'all' => [],
    ];

    /**
     * Throws specified or subclasses of specified exception inside the generator class so that it can be handled.
     *
     * @param string $throwable
     * @return string command
     *
     * @throws Exception when given value is not a valid exception
     */
    public static function throw(string $throwable): string
    {
        if (is_a($throwable, Throwable::class, true)) {
            return __FUNCTION__ . ':' . $throwable;
        }
        throw new Error('Invalid value for throwable, it must extend Throwable class');
    }

    public static function __callStatic($name, $arguments)
    {
        if (key_exists($name, static::$commands)) {
            return $name . ':' . implode($arguments, ',');
        }
        throw new Exception('Invalid command name');
    }
}
