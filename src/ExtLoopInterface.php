<?php

namespace React\EventLoop;

/**
 * Temporary extended loop interface to allow non-BC addition of features.
 * This interface shall be removed when preparing for event-loop v2.
 */
interface ExtLoopInterface extends LoopInterface
{
    /**
     * References a previously dereferenced stream or timer.
     *
     * @param resource|TimerInterface
     * @return void
     * @throws \InvalidArgumentException
     */
    public function reference($streamOrTimer);
    
    /**
     * Dereferences a stream or timer.
     *
     * A dereferenced stream or timer does not keep the loop
     * ticking if dereferenced streams or timers are the only
     * thing that would keep the loop ticking.
     *
     * @param resource|TimerInterface
     * @return void
     * @throws \InvalidArgumentException
     */
    public function dereference($streamOrTimer);
}
