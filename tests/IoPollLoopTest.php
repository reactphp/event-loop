<?php

namespace React\Tests\EventLoop;

use React\EventLoop\IoPollLoop;

class IoPollLoopTest extends \React\Tests\EventLoop\AbstractLoopTest
{
    public function createLoop()
    {
        if (!\class_exists('Io\Poll\Context', false)) {
            $this->markTestSkipped('IOPollLoop tests skipped because IO Poll is not available.');
        }

        return new IoPollLoop();
    }
}
