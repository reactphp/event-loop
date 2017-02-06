<?php

/**
 * Timer.php
 *
 */
namespace React\EventLoop\Timer;

use React\EventLoop\LoopInterface;

/**
 * Class Timer
 *
 * @package React\EventLoop\Timer
 */
class Timer implements TimerInterface
{
    /**
     * @const float MIN_INTERVAL
     */
    const MIN_INTERVAL = 0.000001;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * @var float $interval
     */
    protected $interval;

    /**
     * @var callable $callback
     */
    protected $callback;

    /**
     * @var bool $periodic
     */
    protected $periodic;

    /**
     * @var mixed $data
     */
    protected $data;

    /**
     * Constructor initializes the fields of the Timer
     *
     * @param LoopInterface $loop     The loop with which this timer is associated
     * @param float         $interval The interval after which this timer will execute, in seconds
     * @param callable      $callback The callback that will be executed when this timer elapses
     * @param bool          $periodic Whether the time is periodic
     * @param mixed         $data     Arbitrary data associated with timer
     */
    public function __construct(LoopInterface $loop, $interval, callable $callback, $periodic = false, $data = null)
    {
        if ($interval < self::MIN_INTERVAL) {
            $interval = self::MIN_INTERVAL;
        }

        $this->loop = $loop;
        $this->interval = (float) $interval;
        $this->callback = $callback;
        $this->periodic = (bool) $periodic;
        $this->data = null;
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
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function isPeriodic()
    {
        return $this->periodic;
    }

    /**
     * {@inheritdoc}
     */
    public function isActive()
    {
        return $this->loop->isTimerActive($this);
    }

    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->loop->cancelTimer($this);
    }
}
