<?php

namespace React\EventLoop;

use React\EventLoop\Signal\Pcntl;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Timer\Timers;

/**
 * A `stream_select()` based event loop.
 *
 * This uses the [`stream_select()`](http://php.net/manual/en/function.stream-select.php)
 * function and is the only implementation which works out of the box with PHP.
 * It does a simple `select` system call.
 * It's not the most performant of loops, but still does the job quite well.
 *
 * @link http://php.net/manual/en/function.stream-select.php
 */
class StreamSelectLoop implements LoopInterface
{
    const MICROSECONDS_PER_SECOND = 1000000;

    private $futureTickQueue;
    private $timers;
    private $readStreams = [];
    private $readListeners = [];
    private $writeStreams = [];
    private $writeListeners = [];
    private $running;
    private $pcntl = false;
    private $signals;

    public function __construct()
    {
        $this->futureTickQueue = new FutureTickQueue();
        $this->timers = new Timers();
        $this->pcntl = extension_loaded('pcntl');
        $this->signals = new SignalsHandler(
            $this,
            function ($signal) {
                \pcntl_signal($signal, $f = function ($signal) use (&$f) {
                    $this->signals->call($signal);
                    // Ensure there are two copies of the callable around until it has been executed.
                    // For more information see: https://bugs.php.net/bug.php?id=62452
                    // Only an issue for PHP 5, this hack can be removed once PHP 5 support has been dropped.
                    $g = $f;
                    $f = $g;
                });
            },
            function ($signal) {
                if ($this->signals->count($signal) === 0) {
                    \pcntl_signal($signal, SIG_DFL);
                }
            }
        );
    }

    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->readStreams[$key])) {
            $this->readStreams[$key] = $stream;
            $this->readListeners[$key] = $listener;
        }
    }

    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->writeStreams[$key])) {
            $this->writeStreams[$key] = $stream;
            $this->writeListeners[$key] = $listener;
        }
    }

    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        unset(
            $this->readStreams[$key],
            $this->readListeners[$key]
        );
    }

    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        unset(
            $this->writeStreams[$key],
            $this->writeListeners[$key]
        );
    }

    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($interval, $callback, false);

        $this->timers->add($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($interval, $callback, true);

        $this->timers->add($timer);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        $this->timers->cancel($timer);
    }

    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function addSignal($signal, callable $listener)
    {
        if ($this->pcntl === false) {
            throw new \BadMethodCallException('Event loop feature "signals" isn\'t supported by the "StreamSelectLoop"');
        }

        $this->signals->add($signal, $listener);
    }

    public function removeSignal($signal, callable $listener)
    {
        $this->signals->remove($signal, $listener);
    }

    public function run()
    {
        $this->running = true;

        while ($this->running) {
            $this->futureTickQueue->tick();

            $this->timers->tick();

            // Future-tick queue has pending callbacks ...
            if (!$this->running || !$this->futureTickQueue->isEmpty()) {
                $timeout = 0;

            // There is a pending timer, only block until it is due ...
            } elseif ($scheduledAt = $this->timers->getFirst()) {
                $timeout = $scheduledAt - $this->timers->getTime();
                if ($timeout < 0) {
                    $timeout = 0;
                } else {
                    /*
                     * round() needed to correct float error:
                     * https://github.com/reactphp/event-loop/issues/48
                     */
                    $timeout = round($timeout * self::MICROSECONDS_PER_SECOND);
                }

            // The only possible event is stream activity, so wait forever ...
            } elseif ($this->readStreams || $this->writeStreams) {
                $timeout = null;

            // There's nothing left to do ...
            } else {
                break;
            }

            $this->waitForStreamActivity($timeout);
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    /**
     * Wait/check for stream activity, or until the next timer is due.
     */
    private function waitForStreamActivity($timeout)
    {
        $read  = $this->readStreams;
        $write = $this->writeStreams;

        $available = $this->streamSelect($read, $write, $timeout);
        if ($this->pcntl) {
            \pcntl_signal_dispatch();
        }
        if (false === $available) {
            // if a system call has been interrupted,
            // we cannot rely on it's outcome
            return;
        }

        foreach ($read as $stream) {
            $key = (int) $stream;

            if (isset($this->readListeners[$key])) {
                call_user_func($this->readListeners[$key], $stream);
            }
        }

        foreach ($write as $stream) {
            $key = (int) $stream;

            if (isset($this->writeListeners[$key])) {
                call_user_func($this->writeListeners[$key], $stream);
            }
        }
    }

    /**
     * Emulate a stream_select() implementation that does not break when passed
     * empty stream arrays.
     *
     * @param array        &$read   An array of read streams to select upon.
     * @param array        &$write  An array of write streams to select upon.
     * @param integer|null $timeout Activity timeout in microseconds, or null to wait forever.
     *
     * @return integer|false The total number of streams that are ready for read/write.
     * Can return false if stream_select() is interrupted by a signal.
     */
    protected function streamSelect(array &$read, array &$write, $timeout)
    {
        if ($read || $write) {
            $except = null;

            // suppress warnings that occur, when stream_select is interrupted by a signal
            return @stream_select($read, $write, $except, $timeout === null ? null : 0, $timeout);
        }

        $timeout && usleep($timeout);

        return 0;
    }

    /**
     * Iterate over signal listeners for the given signal
     * and call each of them with the signal as first
     * argument.
     *
     * @param int $signal
     *
     * @return void
     */
    private function handleSignal($signal)
    {
        foreach ($this->signals[$signal] as $listener) {
            \call_user_func($listener, $signal);
        }
    }
}
