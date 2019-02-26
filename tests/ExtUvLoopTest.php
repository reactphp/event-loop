<?php

namespace React\Tests\EventLoop;

use React\EventLoop\ExtUvLoop;

/**
 * @requires extension uv
 */
class ExtUvLoopTest extends AbstractForkableLoopTest
{
    public function createLoop()
    {
        if (!function_exists('uv_loop_new')) {
            $this->markTestSkipped('uv tests skipped because ext-uv is not installed.');
        }

        return new ExtUvLoop();
    }
}
