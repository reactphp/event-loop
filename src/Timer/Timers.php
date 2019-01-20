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
    private $sorted = true;

    public function updateTime()
    {
        return $this->time = \microtime(true);
    }

    public function getTime()
    {
        return $this->time ?: $this->updateTime();
    }

    public function add(TimerInterface $timer)
    {
        $id = \spl_object_hash($timer);
        $this->timers[$id] = $timer;
        $this->schedule[$id] = $timer->getInterval() + \microtime(true);
        $this->sorted = false;
    }

    public function contains(TimerInterface $timer)
    {
        return isset($this->timers[\spl_object_hash($timer)]);
    }

    public function cancel(TimerInterface $timer)
    {
        $id = \spl_object_hash($timer);
        unset($this->timers[$id], $this->schedule[$id]);
    }

    public function getFirst()
    {
        // ensure timers are sorted to simply accessing next (first) one
        if (!$this->sorted) {
            $this->sorted = true;
            \asort($this->schedule);
        }

        return \reset($this->schedule);
    }

    public function isEmpty()
    {
        return $this->count() === 0;
    }

    public function count()
    {
        return \count($this->timers);
    }

    public function tick()
    {
        // ensure timers are sorted so we can execute in order
        if (!$this->sorted) {
            $this->sorted = true;
            \asort($this->schedule);
        }

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
            \call_user_func($timer->getCallback(), $timer);

            // re-schedule if this is a periodic timer and it has not been cancelled explicitly already
            if ($timer->isPeriodic() && isset($this->timers[$id])) {
                $this->schedule[$id] = $timer->getInterval() + $time;
                $this->sorted = false;
            } else {
                unset($this->timers[$id], $this->schedule[$id]);
            }
        }
    }
}
