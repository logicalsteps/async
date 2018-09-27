<?php


namespace LogicalSteps\Async;


use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{
    private $startTime;

    private static $colors = [
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'lightGrey' => 37,
        'grey' => 90,
        'lightRed' => 91,
        'lightGreen' => 92,
        'lightYellow' => 93,
        'lightBlue' => 94,
        'lightMagenta' => 95,
        'lightCyan' => 96,
        'white' => 97
    ];

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

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
                $message = ' ┌▀' . $this->backgroundColor(' start ', 'green');
                break;
            case 'end':
                $message = ' └▄' . $this->backgroundColor(' stop ', 'lightRed');
                break;
            default:
                if ($this->consoleColors) {
                    $message = str_replace('await ', $this->color('await ', 'lightGrey'), $message);
                }
                $depth = $context['depth'] ?? 0;
                $depth = $depth
                    ? ' │' . str_repeat(" ", $depth * 2) . $this->color('¤ ', 'yellow')
                    : ' ├' . $this->color('» ', 'lightYellow');
                $message = $depth . $message;
        }
        $elapsed = round((microtime(true) - $this->startTime));
        echo ' ' . str_pad((string)$elapsed, 5, '0', STR_PAD_LEFT)
            . ' ' . $this->backgroundColor("[$level]", 'cyan') . $message . PHP_EOL;
    }

    private function color(string $text, string $color)
    {
        if (!$this->consoleColors) {
            return $text;
        }
        $code = static::$colors[$color];
        return "\033[0;{$code}m{$text}\033[0m";
    }

    private function backgroundColor(string $text, string $color)
    {
        if (!$this->consoleColors) {
            return $text;
        }
        $code = static::$colors[$color] + 10;
        return "\033[0;30m\033[{$code}m{$text}\033[0m";
    }

}
