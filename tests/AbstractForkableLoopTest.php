<?php

namespace React\Tests\EventLoop;

use React\EventLoop\ForkableLoopInterface;

/**
 * @method ForkableLoopInterface createLoop()
 */
abstract class AbstractForkableLoopTest extends AbstractLoopTest
{
    public function testShouldReturnNewLoopOnRecreateForChildProcess()
    {
        $loop = $this->createLoop();
        $new = $loop->recreateForChildProcess();

        $this->assertNotSame($loop, $new);
    }

    public function testShouldDisposeTheLoopOnRecreateForChild()
    {
        $read = fopen('/dev/urandom', 'r');
        $write = fopen('/dev/null', 'w');
        $loop = $this->createLoop();
        $never = $this->expectCallableNever();

        $loop->addReadStream($read, $never);
        $loop->addWriteStream($write, $never);
        $loop->addTimer(0.1, $never);
        $loop->futureTick($never);

        $loop->recreateForChildProcess();
        $this->tickLoop($loop);
    }

    public function testShouldStopTheExistingLoopOnRecreateForChildProcess()
    {
        $loop = $this->createLoop();
        $futureTickCalled = false;

        $loop->futureTick(function() use ($loop, &$futureTickCalled) {
            $loop->recreateForChildProcess();

            $loop->futureTick(function() use ($loop, &$futureTickCalled) {
                $futureTickCalled = true;
                $loop->stop();
            });
        });

        $this->assertFalse($futureTickCalled);
    }
}
