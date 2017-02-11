<?php

namespace React\Tests\EventLoop;

use React\EventLoop\GlobalLoop;

class GlobalLoopTest extends TestCase
{
    private static $state;

    public function setUp()
    {
        self::$state = GlobalLoop::$loop;
        GlobalLoop::$loop = null;
    }

    public function tearDown()
    {
        GlobalLoop::$loop = self::$state;
        GlobalLoop::setFactory(['React\EventLoop\Factory', 'create']);
    }

    public function testCreatesDefaultLoop()
    {
        $this->assertNull(GlobalLoop::$loop);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', GlobalLoop::get());
    }

    public function testCreatesCustomLoopWithFactory()
    {
        $this->assertNull(GlobalLoop::$loop);

        $loop = $this->getMockBuilder('React\EventLoop\LoopInterface')
            ->getMock();

        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue($loop));

        GlobalLoop::setFactory($factory);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', GlobalLoop::get());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage The GlobalLoop factory must return an instance of LoopInterface but returned NULL.
     */
    public function testThrowsExceptionWhenFactoryDoesNotReturnALoopInterface()
    {
        $this->assertNull(GlobalLoop::$loop);

        $factory = $this->createCallableMock();
        $factory
            ->expects($this->once())
            ->method('__invoke');

        GlobalLoop::setFactory($factory);

        $this->assertInstanceOf('React\EventLoop\LoopInterface', GlobalLoop::get());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Setting a factory after the global loop has been created is not allowed.
     */
    public function testThrowsExceptionWhenSettingAFactoryAfterLoopIsCreated()
    {
        $this->assertNull(GlobalLoop::$loop);

        GlobalLoop::get();

        $this->assertNotNull(GlobalLoop::$loop);

        GlobalLoop::setFactory($this->expectCallableNever());
    }
}
