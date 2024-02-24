<?php

namespace React\Tests\EventLoop;

use PHPUnit\Framework\TestCase as BaseTestCase;
use React\EventLoop\LoopInterface;

class TestCase extends BaseTestCase
{
    protected function expectCallableExactly($amount)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->exactly($amount))
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        if (method_exists('PHPUnit\Framework\MockObject\MockBuilder', 'addMethods')) {
            // PHPUnit 9+
            return $this->getMockBuilder('stdClass')->addMethods(['__invoke'])->getMock();
        } else {
            // legacy PHPUnit
            return $this->getMockBuilder('stdClass')->setMethods(['__invoke'])->getMock();
        }
    }

    protected function tickLoop(LoopInterface $loop)
    {
        $loop->futureTick(function () use ($loop) {
            $loop->stop();
        });

        $loop->run();
    }
}
