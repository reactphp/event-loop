<?php

namespace React\EventLoop\Timer;

use React\EventLoop\TimerInterface;

/**
 * A scheduler implementation that can hold multiple timer instances
 *
 * This class should only be used internally, see TimerInterface instead.
 *
 * @see TimerInterface
 * @internal
 */
final class Timers
{
    private $time;
    private $timers = array();
    private $schedule = array();

    public function updateTime()
    {
        return $this->time = microtime(true);
    }

    public function getTime()
    {
        return $this->time ?: $this->updateTime();
    }

    public function add(TimerInterface $timer)
    {
        $id = spl_object_hash($timer);
        $this->timers[$id] = $timer;
        $this->schedule[$id] = $timer->getInterval() + microtime(true);
        asort($this->schedule);
    }

    public function contains(TimerInterface $timer)
    {
        return isset($this->timers[spl_oject_hash($timer)]);
    }

    public function cancel(TimerInterface $timer)
    {
        $id = spl_object_hash($timer);
        unset($this->timers[$id], $this->schedule[$id]);
    }

    public function getFirst()
    {
        return reset($this->schedule);
    }

    public function isEmpty()
    {
        return count($this->timers) === 0;
    }

    public function tick()
    {
        $time = $this->updateTime();

        foreach ($this->schedule as $id => $scheduled) {
            // schedule is ordered, so loop until first timer that is not scheduled for execution now
            if ($scheduled >= $time) {
                break;
            }

            // skip any timers that are removed while we process the current schedule
            if (!isset($this->schedule[$id]) || $this->schedule[$id] !== $scheduled) {
                continue;
            }

            $timer = $this->timers[$id];
            call_user_func($timer->getCallback(), $timer);

            // re-schedule if this is a periodic timer and it has not been cancelled explicitly already
            if ($timer->isPeriodic() && isset($this->timers[$id])) {
                $this->schedule[$id] = $timer->getInterval() + $time;
                asort($this->schedule);
            } else {
                unset($this->timers[$id], $this->schedule[$id]);
            }
        }
    }
}
