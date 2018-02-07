<?php

namespace React\EventLoop;

use React\EventLoop\Signal\Pcntl;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\Timers;

/**
 * A `stream_select()` based event loop.
 *
 * This uses the [`stream_select()`](http://php.net/manual/en/function.stream-select.php)
 * function and is the only implementation which works out of the box with PHP.
 *
 * This event loop works out of the box on PHP 5.4 through PHP 7+ and HHVM.
 * This means that no installation is required and this library works on all
 * platforms and supported PHP versions.
 * Accordingly, the [`Factory`](#factory) will use this event loop by default if
 * you do not install any of the event loop extensions listed below.
 *
 * Under the hood, it does a simple `select` system call.
 * This system call is limited to the maximum file descriptor number of
 * `FD_SETSIZE` (platform dependent, commonly 1024) and scales with `O(m)`
 * (`m` being the maximum file descriptor number passed).
 * This means that you may run into issues when handling thousands of streams
 * concurrently and you may want to look into using one of the alternative
 * event loop implementations listed below in this case.
 * If your use case is among the many common use cases that involve handling only
 * dozens or a few hundred streams at once, then this event loop implementation
 * performs really well.
 *
 * If you want to use signal handling (see also [`addSignal()`](#addsignal) below),
 * this event loop implementation requires `ext-pcntl`.
 * This extension is only available for Unix-like platforms and does not support
 * Windows.
 * It is commonly installed as part of many PHP distributions.
 * If this extension is missing (or you're running on Windows), signal handling is
 * not supported and throws a `BadMethodCallException` instead.
 *
 * This event loop is known to rely on wall-clock time to schedule future
 * timers, because a monotonic time source is not available in PHP by default.
 * While this does not affect many common use cases, this is an important
 * distinction for programs that rely on a high time precision or on systems
 * that are subject to discontinuous time adjustments (time jumps).
 * This means that if you schedule a timer to trigger in 30s and then adjust
 * your system time forward by 20s, the timer may trigger in 10s.
 * See also [`addTimer()`](#addtimer) for more details.
 *
 * @link http://php.net/manual/en/function.stream-select.php
 */
final class StreamSelectLoop implements LoopInterface
{
    /** @internal */
    const MICROSECONDS_PER_SECOND = 1000000;

    private $futureTickQueue;
    private $timers;
    private $readStreams = array();
    private $readListeners = array();
    private $writeStreams = array();
    private $writeListeners = array();
    private $running;
    private $pcntl = false;
    private $signals;

    public function __construct()
    {
        $this->futureTickQueue = new FutureTickQueue();
        $this->timers = new Timers();
        $this->pcntl = extension_loaded('pcntl');
        $this->signals = new SignalsHandler();
    }

    public function addReadStream($stream, $listener)
    {
        $key = (int) $stream;

        if (!isset($this->readStreams[$key])) {
            $this->readStreams[$key] = $stream;
            $this->readListeners[$key] = $listener;
        }
    }

    public function addWriteStream($stream, $listener)
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

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, false);

        $this->timers->add($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, true);

        $this->timers->add($timer);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        $this->timers->cancel($timer);
    }

    public function futureTick($listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function addSignal($signal, $listener)
    {
        if ($this->pcntl === false) {
            throw new \BadMethodCallException('Event loop feature "signals" isn\'t supported by the "StreamSelectLoop"');
        }

        $first = $this->signals->count($signal) === 0;
        $this->signals->add($signal, $listener);

        if ($first) {
            \pcntl_signal($signal, array($this->signals, 'call'));
        }
    }

    public function removeSignal($signal, $listener)
    {
        if (!$this->signals->count($signal)) {
            return;
        }

        $this->signals->remove($signal, $listener);

        if ($this->signals->count($signal) === 0) {
            \pcntl_signal($signal, SIG_DFL);
        }
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
                    // Convert float seconds to int microseconds.
                    // Ensure we do not exceed maximum integer size, which may
                    // cause the loop to tick once every ~35min on 32bit systems.
                    $timeout *= self::MICROSECONDS_PER_SECOND;
                    $timeout = $timeout > PHP_INT_MAX ? PHP_INT_MAX : (int)$timeout;
                }

            // The only possible event is stream or signal activity, so wait forever ...
            } elseif ($this->readStreams || $this->writeStreams || !$this->signals->isEmpty()) {
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
     *
     * @param integer|null $timeout Activity timeout in microseconds, or null to wait forever.
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
    private function streamSelect(array &$read, array &$write, $timeout)
    {
        if ($read || $write) {
            $except = null;

            // suppress warnings that occur, when stream_select is interrupted by a signal
            return @stream_select($read, $write, $except, $timeout === null ? null : 0, $timeout);
        }

        $timeout && usleep($timeout);

        return 0;
    }
}
