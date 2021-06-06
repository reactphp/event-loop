<?php

namespace React\Tests\EventLoop;

use React\EventLoop\Factory;
use React\EventLoop\Loop;
use ReflectionClass;

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

    /**
     * @after
     * @before
     */
    public function unsetLoopFromLoopAccessor()
    {
        $ref = new ReflectionClass('\React\EventLoop\Loop');
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null);
        $prop->setAccessible(false);
    }
}
