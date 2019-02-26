<?php

namespace React\Tests\EventLoop;

use React\EventLoop\ExtEvLoop;

/**
 * @requires extension ev
 */
class ExtEvLoopTest extends AbstractForkableLoopTest
{
    public function createLoop()
    {
        if (!class_exists('EvLoop')) {
            $this->markTestSkipped('ExtEvLoop tests skipped because ext-ev extension is not installed.');
        }

        return new ExtEvLoop();
    }
}
