<?php

namespace React\EventLoop;

interface TimerInterface
{
    /**
     * Get the interval after which this timer will execute, in seconds
     *
     * @return float
     */
    public function getInterval(): float;

    /**
     * Get the callback that will be executed when this timer elapses
     *
     * @return callable
     */
    public function getCallback(): callable;

    /**
     * Determine whether the time is periodic
     *
     * @return bool
     */
    public function isPeriodic(): bool;
}
