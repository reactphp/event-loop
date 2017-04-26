<?php

namespace React\EventLoop\Timer;

/**
 * The actual connection implementation for TimerInterface
 *
 * This class should only be used internally, see TimerInterface instead.
 *
 * @see TimerInterface
 * @internal
 */
final class Timer implements TimerInterface
{
    const MIN_INTERVAL = 0.000001;

    private $interval;
    private $callback;
    private $periodic;

    /**
     * Constructor initializes the fields of the Timer
     *
     * @param float         $interval The interval after which this timer will execute, in seconds
     * @param callable      $callback The callback that will be executed when this timer elapses
     * @param bool          $periodic Whether the time is periodic
     */
    public function __construct($interval, callable $callback, $periodic = false)
    {
        if ($interval < self::MIN_INTERVAL) {
            $interval = self::MIN_INTERVAL;
        }

        $this->interval = (float) $interval;
        $this->callback = $callback;
        $this->periodic = (bool) $periodic;
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
