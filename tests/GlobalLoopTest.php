<?php

namespace React\Tests\EventLoop;

use React\EventLoop\GlobalLoop;

class GlobalLoopTest extends TestCase
{
    public function tearDown()
    {
        GlobalLoop::reset();
        GlobalLoop::setFactory(null);
    }

    public function testCreatesDefaultLoop()
    {
        $this->assertInstanceOf('React\EventLoop\LoopInterface', GlobalLoop::get());
    }

    public function testSubsequentGetCallsReturnSameInstance()
    {
        $this->assertSame(GlobalLoop::get(), GlobalLoop::get());
    }

    public function testSubsequentGetCallsReturnNotSameInstanceWhenResetting()
    {
        $loop = GlobalLoop::get();

        GlobalLoop::reset();

        $this->assertNotSame($loop, GlobalLoop::get());
    }

    public function testCreatesLoopWithFactory()
    {
        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')
            ->getMock();

        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($loop));

        GlobalLoop::setFactory($factory);

        $this->assertSame($loop, GlobalLoop::get());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The GlobalLoop factory must return an instance of LoopInterface but returned NULL.
     */
    public function testThrowsExceptionWhenFactoryDoesNotReturnALoopInterface()
    {
        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke');

        GlobalLoop::setFactory($factory);

        GlobalLoop::get();
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Setting a factory after the global loop has been started is not allowed.
     */
    public function testThrowsExceptionWhenSettingAFactoryAfterLoopIsCreated()
    {
        GlobalLoop::get()->run();

        GlobalLoop::setFactory(null);
    }
}
