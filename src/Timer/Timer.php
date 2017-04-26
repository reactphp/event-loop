<?php

namespace React\EventLoop\Timer;

use React\EventLoop\LoopInterface;

class Timer implements TimerInterface
{
    const MIN_INTERVAL = 0.000001;

    protected $loop;
    protected $interval;
    protected $callback;
    protected $periodic;

    /**
     * Constructor initializes the fields of the Timer
     *
     * @param LoopInterface $loop     The loop with which this timer is associated
     * @param float         $interval The interval after which this timer will execute, in seconds
     * @param callable      $callback The callback that will be executed when this timer elapses
     * @param bool          $periodic Whether the time is periodic
     */
    public function __construct(LoopInterface $loop, $interval, callable $callback, $periodic = false)
    {
        if ($interval < self::MIN_INTERVAL) {
            $interval = self::MIN_INTERVAL;
        }

        $this->loop = $loop;
        $this->interval = (float) $interval;
        $this->callback = $callback;
        $this->periodic = (bool) $periodic;
    }

    /**
     * {@inheritdoc}
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * {@inheritdoc}
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * {@inheritdoc}
     */
    public function isPeriodic()
    {
        return $this->periodic;
    }
}
