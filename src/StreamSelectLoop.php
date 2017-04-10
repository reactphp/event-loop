<?php

namespace React\EventLoop;

use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Timer\Timers;

/**
 * A stream_select() based event-loop.
 */
class StreamSelectLoop implements LoopInterface
{
    const MICROSECONDS_PER_SECOND = 1000000;
    const NANOSECONDS_PER_SECOND = 1000000000;
    const NANOSECONDS_PER_MICROSECOND = 1000;

    private $futureTickQueue;
    private $timers;
    private $readStreams = [];
    private $readListeners = [];
    private $writeStreams = [];
    private $writeListeners = [];
    private $running;

    public function __construct()
    {
        $this->futureTickQueue = new FutureTickQueue($this);
        $this->timers = new Timers();
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->readStreams[$key])) {
            $this->readStreams[$key] = $stream;
            $this->readListeners[$key] = $listener;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->writeStreams[$key])) {
            $this->writeStreams[$key] = $stream;
            $this->writeListeners[$key] = $listener;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        unset(
            $this->readStreams[$key],
            $this->readListeners[$key]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        unset(
            $this->writeStreams[$key],
            $this->writeListeners[$key]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function removeStream($stream)
    {
        $this->removeReadStream($stream);
        $this->removeWriteStream($stream);
    }

    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);

        $this->timers->add($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);

        $this->timers->add($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        $this->timers->cancel($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }

    /**
     * {@inheritdoc}
     */
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
                $timeout = max($scheduledAt - $this->timers->getTime(), 0);
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

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }

    /**
     * Wait/check for stream activity, or until the next timer is due.
     *
     * @param float $timeout
     */
    private function waitForStreamActivity($timeout)
    {
        $read  = $this->readStreams;
        $write = $this->writeStreams;

        $available = $this->streamSelect($read, $write, $timeout);
        if (false === $available) {
            // if a system call has been interrupted,
            // we cannot rely on it's outcome
            return;
        }

        foreach ($read as $stream) {
            $key = (int) $stream;

            if (isset($this->readListeners[$key])) {
                call_user_func($this->readListeners[$key], $stream, $this);
            }
        }

        foreach ($write as $stream) {
            $key = (int) $stream;

            if (isset($this->writeListeners[$key])) {
                call_user_func($this->writeListeners[$key], $stream, $this);
            }
        }
    }

    /**
     * Returns integer amount of seconds in $time.
     *
     * @param float $time – time in seconds
     *
     * @return int
     */
    private static function getSeconds($time)
    {
        /*
         * Workaround for PHP int overflow:
         * (float)PHP_INT_MAX == PHP_INT_MAX => true
         * (int)(float)PHP_INT_MAX == PHP_INT_MAX => false
         * (int)(float)PHP_INT_MAX == PHP_INT_MIN => true
         */
        if ($time == PHP_INT_MAX) {
            return PHP_INT_MAX;
        }

        return intval(floor($time));
    }

    /**
     * Returns integer amount of microseconds in $time.
     *
     * @param float $time – time in seconds
     *
     * @return int
     */
    private static function getMicroseconds($time)
    {
        $fractional = fmod($time, 1);
        $microseconds = round($fractional * self::MICROSECONDS_PER_SECOND);

        return intval($microseconds);
    }

    /**
     * Returns integer amount of nanoseconds in $time.
     * The precision is 1 microsecond.
     *
     * @param float $time – time in seconds
     *
     * @return int
     */
    private static function getNanoseconds($time)
    {
        return intval(self::getMicroseconds($time) * self::NANOSECONDS_PER_MICROSECOND);
    }

    /**
     * Emulate a stream_select() implementation that does not break when passed
     * empty stream arrays.
     *
     * @param array        &$read   An array of read streams to select upon.
     * @param array        &$write  An array of write streams to select upon.
     * @param float|null   $timeout Activity timeout in seconds, or null to wait forever.
     *
     * @return integer|false The total number of streams that are ready for read/write.
     * Can return false if stream_select() is interrupted by a signal.
     */
    protected function streamSelect(array &$read, array &$write, $timeout)
    {
        $seconds = $timeout === null ? null : self::getSeconds($timeout);
        $microseconds = $timeout === null ? 0 : self::getMicroseconds($timeout);
        $nanoseconds = $timeout === null ? 0 : self::getNanoseconds($timeout);

        if ($read || $write) {
            $except = [];

            return $this->doSelectStream($read, $write, $except, $seconds, $microseconds);
        }

        if ($timeout !== null) {
            $this->sleep($seconds, $nanoseconds);
        }

        return 0;
    }

    /**
     * Proxy for built-in stream_select method.
     *
     * @param array $read
     * @param array $write
     * @param array $except
     * @param int|null $seconds
     * @param int $microseconds
     *
     * @return int
     */
    protected function doSelectStream(array &$read, array &$write, array &$except, $seconds, $microseconds)
    {
        // suppress warnings that occur, when stream_select is interrupted by a signal
        return @stream_select($read, $write, $except, $seconds, $microseconds);
    }

    /**
     * Sleeps for $seconds and $nanoseconds.
     *
     * @param int $seconds
     * @param int $nanoseconds
     */
    protected function sleep($seconds, $nanoseconds = 0)
    {
        if ($seconds > 0 || $nanoseconds > 0) {
            time_nanosleep($seconds, $nanoseconds);
        }
    }
}
