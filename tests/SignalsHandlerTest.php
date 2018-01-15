<?php

namespace React\Tests\EventLoop;

use React\EventLoop\Factory;
use React\EventLoop\SignalsHandler;

final class SignalsHandlerTest extends TestCase
{
    public function testEmittedEventsAndCallHandling()
    {
        $callCount = 0;
        $onCount = 0;
        $func = function () use (&$callCount) {
            $callCount++;
        };
        $signals = new SignalsHandler(
            Factory::create(),
            function () use (&$onCount) {
                $onCount++;
            }
        );

        $this->assertSame(0, $callCount);
        $this->assertSame(0, $onCount);

        $signals->add(SIGUSR1, $func);
        $this->assertSame(0, $callCount);
        $this->assertSame(1, $onCount);

        $signals->add(SIGUSR1, $func);
        $this->assertSame(0, $callCount);
        $this->assertSame(1, $onCount);

        $signals->add(SIGUSR1, $func);
        $this->assertSame(0, $callCount);
        $this->assertSame(1, $onCount);

        $signals->call(SIGUSR1);
        $this->assertSame(1, $callCount);
        $this->assertSame(1, $onCount);

        $signals->add(SIGUSR2, $func);
        $this->assertSame(1, $callCount);
        $this->assertSame(2, $onCount);

        $signals->add(SIGUSR2, $func);
        $this->assertSame(1, $callCount);
        $this->assertSame(2, $onCount);

        $signals->call(SIGUSR2);
        $this->assertSame(2, $callCount);
        $this->assertSame(2, $onCount);

        $signals->remove(SIGUSR2, $func);
        $this->assertSame(2, $callCount);
        $this->assertSame(2, $onCount);

        $signals->remove(SIGUSR2, $func);
        $this->assertSame(2, $callCount);
        $this->assertSame(2, $onCount);

        $signals->call(SIGUSR2);
        $this->assertSame(2, $callCount);
        $this->assertSame(2, $onCount);

        $signals->remove(SIGUSR1, $func);
        $this->assertSame(2, $callCount);
        $this->assertSame(2, $onCount);

        $signals->call(SIGUSR1);
        $this->assertSame(2, $callCount);
        $this->assertSame(2, $onCount);
    }
}
