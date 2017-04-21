<?php

namespace React\Tests\EventLoop\Timer;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\Tests\EventLoop\TestCase;

abstract class AbstractTimerTest extends TestCase
{
    /**
     * @return LoopInterface
     */
    abstract public function createLoop();

    public function testAddTimer()
    {
        // usleep is intentionally high

        $loop = $this->createLoop();

        $loop->addTimer(0.001, $this->expectCallableOnce());
        usleep(1000);
        $this->tickLoop($loop);
    }

    public function testAddPeriodicTimer()
    {
        $loop = $this->createLoop();

        $loop->addPeriodicTimer(0.001, $this->expectCallableExactly(3));
        usleep(1000);
        $this->tickLoop($loop);
        usleep(1000);
        $this->tickLoop($loop);
        usleep(1000);
        $this->tickLoop($loop);
    }

    public function testAddPeriodicTimerWithCancel()
    {
        $loop = $this->createLoop();

        $timer = $loop->addPeriodicTimer(0.001, $this->expectCallableExactly(2));

        usleep(1000);
        $this->tickLoop($loop);
        usleep(1000);
        $this->tickLoop($loop);

        $timer->cancel();

        usleep(1000);
        $this->tickLoop($loop);
    }

    public function testAddPeriodicTimerCancelsItself()
    {
        $i = 0;

        $loop = $this->createLoop();

        $loop->addPeriodicTimer(0.001, function ($timer) use (&$i) {
            $i++;

            if ($i == 2) {
                $timer->cancel();
            }
        });

        usleep(1000);
        $this->tickLoop($loop);
        usleep(1000);
        $this->tickLoop($loop);
        usleep(1000);
        $this->tickLoop($loop);

        $this->assertSame(2, $i);
    }

    public function testIsTimerActive()
    {
        $loop = $this->createLoop();

        $timer = $loop->addPeriodicTimer(0.001, function () {});

        $this->assertTrue($loop->isTimerActive($timer));

        $timer->cancel();

        $this->assertFalse($loop->isTimerActive($timer));
    }

    public function testMinimumIntervalOneMicrosecond()
    {
        $loop = $this->createLoop();

        $timer = $loop->addTimer(0, function () {});

        $this->assertEquals(0.000001, $timer->getInterval());
    }

    public function testCancelNonexistentTimer()
    {
        $loop = $this->createLoop();

        $timer = new Timer($loop, 1, function(){});

        $loop->cancelTimer($timer);
    }
}
