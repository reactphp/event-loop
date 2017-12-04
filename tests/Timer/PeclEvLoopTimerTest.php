<?php

namespace React\Tests\EventLoop\Timer;

use React\EventLoop\PeclEvLoop;

class PeclEvLoopTimerTest extends AbstractTimerTest
{
    public function createLoop()
    {
        if (!class_exists('EvLoop')) {
            $this->markTestSkipped('pecl-ev tests skipped because ext-ev is not installed.');
        }

        return new PeclEvLoop();
    }
}