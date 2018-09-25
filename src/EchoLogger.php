<?php


namespace LogicalSteps\Async;


use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        echo "[$level] $message\n";
    }
}