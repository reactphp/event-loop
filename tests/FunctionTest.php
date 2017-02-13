<?php

namespace React\Tests\EventLoop;

use React\EventLoop;
use React\EventLoop\GlobalLoop;

class FunctionTest extends TestCase
{
    private static $state;
    private $globalLoop;

    public function setUp()
    {
        $globalLoop = $this->getMockBuilder('React\EventLoop\LoopInterface')
            ->getMock();

        self::$state = GlobalLoop::$loop;
        GlobalLoop::$loop = $this->globalLoop = $globalLoop;
    }

    public function tearDown()
    {
        $this->globalLoop = null;

        GlobalLoop::$loop = self::$state;
    }

    public function createStream()
    {
        return fopen('php://temp', 'r+');
    }

    public function testAddReadStream()
    {
        $stream = $this->createStream();
        $listener = function() {};

        $this->globalLoop
            ->expects($this->once())
            ->method('addReadStream')
            ->with($stream, $listener);

        EventLoop\addReadStream($stream, $listener);
    }

    public function testAddWriteStream()
    {
        $stream = $this->createStream();
        $listener = function() {};

        $this->globalLoop
            ->expects($this->once())
            ->method('addWriteStream')
            ->with($stream, $listener);

        EventLoop\addWriteStream($stream, $listener);
    }

    public function testRemoveReadStream()
    {
        $stream = $this->createStream();

        $this->globalLoop
            ->expects($this->once())
            ->method('removeReadStream')
            ->with($stream);

        EventLoop\removeReadStream($stream);
    }

    public function testRemoveWriteStream()
    {
        $stream = $this->createStream();

        $this->globalLoop
            ->expects($this->once())
            ->method('removeWriteStream')
            ->with($stream);

        EventLoop\removeWriteStream($stream);
    }

    public function testRemoveStream()
    {
        $stream = $this->createStream();

        $this->globalLoop
            ->expects($this->once())
            ->method('removeStream')
            ->with($stream);

        EventLoop\removeStream($stream);
    }

    public function testAddTimer()
    {
        $interval = 1;
        $listener = function() {};

        $this->globalLoop
            ->expects($this->once())
            ->method('addTimer')
            ->with($interval, $listener);

        EventLoop\addTimer($interval, $listener);
    }

    public function testAddPeriodicTimer()
    {
        $interval = 1;
        $listener = function() {};

        $this->globalLoop
            ->expects($this->once())
            ->method('addPeriodicTimer')
            ->with($interval, $listener);

        EventLoop\addPeriodicTimer($interval, $listener);
    }
}
