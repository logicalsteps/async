<?php


namespace LogicalSteps\Async;


use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{
    public $consoleColors = true;

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        switch ($message) {
            case 'start':
                $message = ' ┌ ⚑ start';
                break;
            case 'end':
                $message = ' └ ■ stop';
                break;
            default:
                if ($this->consoleColors) {
                    $message = str_replace('await ', $this->lightGrey('await '), $message);
                }
                $depth = $context['depth'] ?? 0;
                $depth = $depth ? ' │' . str_repeat(" ", $depth) . '├ ' : ' ├ ';
                $message = $depth . $message;
        }
        echo $this->cyanBackground("[$level]") . $message . PHP_EOL;
    }

    private function cyanBackground(string $text)
    {
        if (!$this->consoleColors) {
            return $text;
        }

        return "\033[0;30m\033[46m{$text}\033[0m";
    }

    private function lightGrey(string $text)
    {
        return "\033[0;37m{$text}\033[0m";
    }
}