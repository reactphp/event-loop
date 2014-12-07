<?php

/**
 * FutureTickQueue.php
 *
 */
namespace React\EventLoop\Tick;

/**
 * Class FutureTickQueue
 *
 * @package React\EventLoop\Tick
 */
class FutureTickQueue extends TickQueue
{
    /**
     * Flush the callback queue.
     */
    public function tick()
    {
        // Only invoke as many callbacks as were on the queue when tick() was called.
        $count = $this->queue->count();

        while ($count--) {
            call_user_func(
                $this->queue->dequeue(),
                $this->eventLoop
            );
        }
    }
}
