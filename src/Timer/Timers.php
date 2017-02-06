<?php

/**
 * Timers.php
 *
 */
namespace React\EventLoop\Timer;

use SplObjectStorage;
use SplPriorityQueue;

/**
 * Class Timers
 *
 * @package React\EventLoop\Timer
 */
class Timers
{
    /**
     * @var float $time Microtime
     */
    private $time;

    /**
     * @var \SplObjectStorage $timers
     */
    private $timers;

    /**
     * @var \SplPriorityQueue $scheduler
     */
    private $scheduler;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->timers = new SplObjectStorage();
        $this->scheduler = new SplPriorityQueue();
    }

    /**
     * Sets the time property to current microtime
     *
     * @return float $time
     */
    public function updateTime()
    {
        return $this->time = microtime(true);
    }

    /**
     * Returns the time property, but updates when it doesn't have a value
     *
     * @return float $time
     */
    public function getTime()
    {
        return $this->time ?: $this->updateTime();
    }

    /**
     * @param TimerInterface $timer
     */
    public function add(TimerInterface $timer)
    {
        $interval = $timer->getInterval();
        $scheduledAt = $interval + microtime(true);

        $this->timers->attach($timer, $scheduledAt);
        $this->scheduler->insert($timer, -$scheduledAt);
    }

    /**
     * @param TimerInterface $timer
     * @see SplObjectStorage::contains
     *
     * @return bool
     */
    public function contains(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    /**
     * @param TimerInterface $timer
     * @see SplObjectStorage::detach
     */
    public function cancel(TimerInterface $timer)
    {
        $this->timers->detach($timer);
    }

    /**
     * @return TimerInterface|null $first
     */
    public function getFirst()
    {
        while ($this->scheduler->count()) {
            $timer = $this->scheduler->top();

            if ($this->timers->contains($timer)) {
                return $this->timers[$timer];
            }

            $this->scheduler->extract();
        }

        return null;
    }

    /**
     * @return bool $isEmpty
     */
    public function isEmpty()
    {
        return count($this->timers) === 0;
    }

    /**
     * Tick
     */
    public function tick()
    {
        $time = $this->updateTime();
        $timers = $this->timers;
        $scheduler = $this->scheduler;

        while (!$scheduler->isEmpty()) {
            $timer = $scheduler->top();

            if (!isset($timers[$timer])) {
                $scheduler->extract();
                $timers->detach($timer);

                continue;
            }

            if ($timers[$timer] >= $time) {
                break;
            }

            $scheduler->extract();
            call_user_func($timer->getCallback(), $timer);

            if ($timer->isPeriodic() && isset($timers[$timer])) {
                $timers[$timer] = $scheduledAt = $timer->getInterval() + $time;
                $scheduler->insert($timer, -$scheduledAt);
            } else {
                $timers->detach($timer);
            }
        }
    }
}
