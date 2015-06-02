<?php

namespace React\Tests\EventLoop;

use React\EventLoop\ExtEvLoop;

class ExtEvLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        if (!class_exists('\EvLoop')) {
            $this->markTestSkipped('ev tests skipped because ext-ev is not installed.');
        }

        return new ExtEvLoop();
    }

    public function testExtEvConstructor()
    {
        $loop = new ExtEvLoop();
    }
}
