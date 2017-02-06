<?php

/**
 * NextTickQueue.php
 *
 */
namespace React\EventLoop\Tick;

/**
 * Class NextTickQueue
 *
 * @package React\EventLoop\Tick
 */
class NextTickQueue extends TickQueue
{
    /**
     * Flush the callback queue.
     */
    public function tick()
    {
        while (!$this->queue->isEmpty()) {
            call_user_func(
                $this->queue->dequeue(),
                $this->eventLoop
            );
        }
    }
}
