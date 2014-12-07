<?php

/**
 * Loop.php
 *
 */
namespace React\EventLoop;

use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Tick\NextTickQueue;

/**
 * Abstract loop base class used to deduplicate all logic among LoopInterface implementations
 *
 * @package React\EventLoop
 */
abstract class Loop
{
    /**
     * @const int MICROSECONDS_PER_SECOND
     */
    const MICROSECONDS_PER_SECOND = 1000000;

    /**
     * @var Tick\NextTickQueue $nextTickQueue
     */
    protected $nextTickQueue;

    /**
     * @var Tick\FutureTickQueue $futureTickQueue
     */
    protected $futureTickQueue;

    /**
     * @var bool $running
     */
    protected $running = false;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->nextTickQueue = new NextTickQueue($this);
        $this->futureTickQueue = new FutureTickQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }
}