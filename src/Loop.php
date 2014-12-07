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
     * @var Tick\NextTickQueue $nextTickQueue
     */
    protected $nextTickQueue;

    /**
     * @var Tick\FutureTickQueue $futureTickQueue
     */
    protected $futureTickQueue;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->nextTickQueue = new NextTickQueue($this);
        $this->futureTickQueue = new FutureTickQueue($this);
    }
}