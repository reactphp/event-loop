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
        list($readIn, $readOut) = $this->createSocketPair();
        list($writeIn) = $this->createSocketPair();
        $loop = $this->createLoop();
        $never = $this->expectCallableNever();

        fwrite($readIn, 'Test');

        $loop->addReadStream($readOut, $never);
        $loop->addWriteStream($writeIn, $never);
        $loop->addTimer(0.1, $never);
        $loop->futureTick($never);

        $loop->recreateForChildProcess();
        $this->tickLoop($loop);
    }

    public function testShouldStopTheExistingLoopOnRecreateForChildProcess()
    {
        $loop = $this->createLoop();
        $never = $this->expectCallableNever();
        $result = null;

        $loop->futureTick(function() use ($loop, $never, &$result) {
            $result = $loop->recreateForChildProcess();

            $loop->futureTick($never);
            $loop->futureTick(array($loop, 'stop'));
        });

        $loop->run();

        $this->assertInstanceOf(
            'React\EventLoop\ForkableLoopInterface',
            $result
        );

        $this->assertNotSame($loop, $result);
    }
}
