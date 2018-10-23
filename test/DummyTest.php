<?php

namespace LogicalSteps\Async\Test;

use LogicalSteps\Async\Async;

class DummyTest extends \PHPUnit\Framework\TestCase
{
    /**
     * A dummy test that calls a beacon method ensuring the class is autolaoded.
     *
     * @see https://github.com/cpliakas/php-project-starter/issues/19
     * @see https://github.com/cpliakas/php-project-starter/issues/21
     */
    public function testAutoload()
    {
        $class = new Async();
        $this->assertTrue($class->autoloaded());
    }
}
