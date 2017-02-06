<?php

/**
 * TickQueue.php
 *
 */
namespace React\EventLoop\Tick;

use React\EventLoop\LoopInterface;
use SplQueue;

/**
 * TickQueue is an abstract class providing the basic functionality for TickQueue implementations
 *
 * @package React\EventLoop\Tick
 */
abstract class TickQueue
{
    /**
     * @var \React\EventLoop\LoopInterface $eventLoop
     */
    protected $eventLoop;

    /**
     * @var \SplQueue $queue
     */
    protected $queue;

    /**
     * @param LoopInterface $eventLoop The event loop passed as the first parameter to callbacks.
     */
    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;
        $this->queue = new SplQueue();
    }

    /**
     * Add a callback to be invoked on a future tick of the event loop.
     *
     * Callbacks are guaranteed to be executed in the order they are enqueued.
     *
     * @param callable $listener The callback to invoke.
     */
    public function add(callable $listener)
    {
        $this->queue->enqueue($listener);
    }

    /**
     * Flush the callback queue.
     */
    abstract public function tick();

    /**
     * Check if the next tick queue is empty.
     *
     * @return boolean
     */
    public function isEmpty()
    {
        return $this->queue->isEmpty();
    }
}