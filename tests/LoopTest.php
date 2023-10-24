<?php

namespace React\Tests\EventLoop;

use React\EventLoop\Factory;
use React\EventLoop\Loop;

final class LoopTest extends TestCase
{
    /**
     * @dataProvider numberOfTests
     */
    public function testFactoryCreateSetsEventLoopOnLoopAccessor()
    {
        $factoryLoop = Factory::create();
        $accessorLoop = Loop::get();

        self::assertSame($factoryLoop, $accessorLoop);
    }

    /**
     * @dataProvider numberOfTests
     */
    public function testCallingFactoryAfterCallingLoopGetYieldsADifferentInstanceOfTheEventLoop()
    {
        // Note that this behavior isn't wise and highly advised against. Always used Loop::get.
        $accessorLoop = Loop::get();
        $factoryLoop = Factory::create();

        self::assertNotSame($factoryLoop, $accessorLoop);
    }

    /**
     * @dataProvider numberOfTests
     */
    public function testCallingLoopGetShouldAlwaysReturnTheSameEventLoop()
    {
        self::assertSame(Loop::get(), Loop::get());
    }

    /**
     * Run several tests several times to ensure we reset the loop between tests and code is still behavior as expected.
     *
     * @return array<array>
     */
    public function numberOfTests()
    {
        return array(array(), array(), array());
    }

    public function testStaticAddReadStreamCallsAddReadStreamOnLoopInstance()
    {
        $stream = tmpfile();
        $listener = function () { };

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addReadStream')->with($stream, $listener);

        Loop::set($loop);

        Loop::addReadStream($stream, $listener);
    }

    public function testStaticAddReadStreamWithNoDefaultLoopCallsAddReadStreamOnNewLoopInstance()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $stream = stream_socket_server('127.0.0.1:0');
        $listener = function () { };
        Loop::addReadStream($stream, $listener);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }

    public function testStaticAddWriteStreamCallsAddWriteStreamOnLoopInstance()
    {
        $stream = tmpfile();
        $listener = function () { };

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addWriteStream')->with($stream, $listener);

        Loop::set($loop);

        Loop::addWriteStream($stream, $listener);
    }

    public function testStaticAddWriteStreamWithNoDefaultLoopCallsAddWriteStreamOnNewLoopInstance()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $stream = stream_socket_server('127.0.0.1:0');
        $listener = function () { };
        Loop::addWriteStream($stream, $listener);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }

    public function testStaticRemoveReadStreamCallsRemoveReadStreamOnLoopInstance()
    {
        $stream = tmpfile();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('removeReadStream')->with($stream);

        Loop::set($loop);

        Loop::removeReadStream($stream);
    }

    public function testStaticRemoveReadStreamWithNoDefaultLoopIsNoOp()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $stream = tmpfile();
        Loop::removeReadStream($stream);

        $this->assertNull($ref->getValue());
    }

    public function testStaticRemoveWriteStreamCallsRemoveWriteStreamOnLoopInstance()
    {
        $stream = tmpfile();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('removeWriteStream')->with($stream);

        Loop::set($loop);

        Loop::removeWriteStream($stream);
    }

    public function testStaticRemoveWriteStreamWithNoDefaultLoopIsNoOp()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $stream = tmpfile();
        Loop::removeWriteStream($stream);

        $this->assertNull($ref->getValue());
    }

    public function testStaticAddTimerCallsAddTimerOnLoopInstanceAndReturnsTimerInstance()
    {
        $interval = 1.0;
        $callback = function () { };
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addTimer')->with($interval, $callback)->willReturn($timer);

        Loop::set($loop);

        $ret = Loop::addTimer($interval, $callback);

        $this->assertSame($timer, $ret);
    }

    public function testStaticAddTimerWithNoDefaultLoopCallsAddTimerOnNewLoopInstance()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $interval = 1.0;
        $callback = function () { };
        $ret = Loop::addTimer($interval, $callback);

        $this->assertInstanceOf('React\EventLoop\TimerInterface', $ret);
        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }

    public function testStaticAddPeriodicTimerCallsAddPeriodicTimerOnLoopInstanceAndReturnsTimerInstance()
    {
        $interval = 1.0;
        $callback = function () { };
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addPeriodicTimer')->with($interval, $callback)->willReturn($timer);

        Loop::set($loop);

        $ret = Loop::addPeriodicTimer($interval, $callback);

        $this->assertSame($timer, $ret);
    }

    public function testStaticAddPeriodicTimerWithNoDefaultLoopCallsAddPeriodicTimerOnNewLoopInstance()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $interval = 1.0;
        $callback = function () { };
        $ret = Loop::addPeriodicTimer($interval, $callback);

        $this->assertInstanceOf('React\EventLoop\TimerInterface', $ret);
        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }


    public function testStaticCancelTimerCallsCancelTimerOnLoopInstance()
    {
        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('cancelTimer')->with($timer);

        Loop::set($loop);

        Loop::cancelTimer($timer);
    }

    public function testStaticCancelTimerWithNoDefaultLoopIsNoOp()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $timer = $this->getMockBuilder('React\EventLoop\TimerInterface')->getMock();
        Loop::cancelTimer($timer);

        $this->assertNull($ref->getValue());
    }

    public function testStaticFutureTickCallsFutureTickOnLoopInstance()
    {
        $listener = function () { };

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('futureTick')->with($listener);

        Loop::set($loop);

        Loop::futureTick($listener);
    }

    public function testStaticFutureTickWithNoDefaultLoopCallsFutureTickOnNewLoopInstance()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $listener = function () { };
        Loop::futureTick($listener);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }

    public function testStaticAddSignalCallsAddSignalOnLoopInstance()
    {
        $signal = 1;
        $listener = function () { };

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('addSignal')->with($signal, $listener);

        Loop::set($loop);

        Loop::addSignal($signal, $listener);
    }

    public function testStaticAddSignalWithNoDefaultLoopCallsAddSignalOnNewLoopInstance()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Not supported on Windows');
        }

        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $signal = 1;
        $listener = function () { };
        try {
            Loop::addSignal($signal, $listener);
        } catch (\BadMethodCallException $e) {
            $this->markTestSkipped('Skipped: ' . $e->getMessage());
        }

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }

    public function testStaticRemoveSignalCallsRemoveSignalOnLoopInstance()
    {
        $signal = 1;
        $listener = function () { };

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('removeSignal')->with($signal, $listener);

        Loop::set($loop);

        Loop::removeSignal($signal, $listener);
    }

    public function testStaticRemoveSignalWithNoDefaultLoopIsNoOp()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        $signal = 1;
        $listener = function () { };
        Loop::removeSignal($signal, $listener);

        $this->assertNull($ref->getValue());
    }

    public function testStaticRunCallsRunOnLoopInstance()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('run')->with();

        Loop::set($loop);

        Loop::run();
    }

    public function testStaticRunWithNoDefaultLoopCallsRunsOnNewLoopInstance()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        Loop::run();

        $this->assertInstanceOf('React\EventLoop\LoopInterface', $ref->getValue());
    }

    public function testStaticStopCallsStopOnLoopInstance()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $loop->expects($this->once())->method('stop')->with();

        Loop::set($loop);

        Loop::stop();
    }

    public function testStaticStopCallWithNoDefaultLoopIsNoOp()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        Loop::stop();

        $this->assertNull($ref->getValue());
    }

    /**
     * @after
     * @before
     */
    public function unsetLoopFromLoopAccessor()
    {
        $ref = new \ReflectionProperty('React\EventLoop\Loop', 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }
}
