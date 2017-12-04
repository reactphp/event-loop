<?php

namespace React\EventLoop;

use Event;
use EventBase;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use SplObjectStorage;

/**
 * An `ext-libevent` based event loop.
 *
 * This uses the [`libevent` PECL extension](https://pecl.php.net/package/libevent).
 * `libevent` itself supports a number of system-specific backends (epoll, kqueue).
 *
 * This event loop does only work with PHP 5.
 * An [unofficial update](https://github.com/php/pecl-event-libevent/pull/2) for
 * PHP 7 does exist, but it is known to cause regular crashes due to `SEGFAULT`s.
 * To reiterate: Using this event loop on PHP 7 is not recommended.
 * Accordingly, the [`Factory`](#factory) will not try to use this event loop on
 * PHP 7.
 *
 * This event loop is known to trigger a readable listener only if
 * the stream *becomes* readable (edge-triggered) and may not trigger if the
 * stream has already been readable from the beginning.
 * This also implies that a stream may not be recognized as readable when data
 * is still left in PHP's internal stream buffers.
 * As such, it's recommended to use `stream_set_read_buffer($stream, 0);`
 * to disable PHP's internal read buffer in this case.
 * See also [`addReadStream()`](#addreadstream) for more details.
 *
 * @link https://pecl.php.net/package/libevent
 */
final class ExtLibeventLoop implements LoopInterface
{
    /** @internal */
    const MICROSECONDS_PER_SECOND = 1000000;

    private $eventBase;
    private $futureTickQueue;
    private $timerCallback;
    private $timerEvents;
    private $streamCallback;
    private $streamEvents = [];
    private $streamFlags = [];
    private $readListeners = [];
    private $writeListeners = [];
    private $running;
    private $signals;
    private $signalEvents = [];

    public function __construct()
    {
        $this->eventBase = event_base_new();
        $this->futureTickQueue = new FutureTickQueue();
        $this->timerEvents = new SplObjectStorage();

        $this->signals = new SignalsHandler(
            $this,
            function ($signal) {
                $this->signalEvents[$signal] = event_new();
                event_set($this->signalEvents[$signal], $signal, EV_PERSIST | EV_SIGNAL, $f = function () use ($signal, &$f) {
                    $this->signals->call($signal);
                    // Ensure there are two copies of the callable around until it has been executed.
                    // For more information see: https://bugs.php.net/bug.php?id=62452
                    // Only an issue for PHP 5, this hack can be removed once PHP 5 support has been dropped.
                    $g = $f;
                    $f = $g;
                });
                event_base_set($this->signalEvents[$signal], $this->eventBase);
                event_add($this->signalEvents[$signal]);
            },
            function ($signal) {
                if ($this->signals->count($signal) === 0) {
                    event_del($this->signalEvents[$signal]);
                    event_free($this->signalEvents[$signal]);
                    unset($this->signalEvents[$signal]);
                }
            }
        );

        $this->createTimerCallback();
        $this->createStreamCallback();
    }

    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->readListeners[$key])) {
            $this->readListeners[$key] = $listener;
            $this->subscribeStreamEvent($stream, EV_READ);
        }
    }

    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->writeListeners[$key])) {
            $this->writeListeners[$key] = $listener;
            $this->subscribeStreamEvent($stream, EV_WRITE);
        }
    }

    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->readListeners[$key])) {
            unset($this->readListeners[$key]);
            $this->unsubscribeStreamEvent($stream, EV_READ);
        }
    }

    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->writeListeners[$key])) {
            unset($this->writeListeners[$key]);
            $this->unsubscribeStreamEvent($stream, EV_WRITE);
        }
    }

    private function removeStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->streamEvents[$key])) {
            $event = $this->streamEvents[$key];

            event_del($event);
            event_free($event);

            unset(
                $this->streamFlags[$key],
                $this->streamEvents[$key],
                $this->readListeners[$key],
                $this->writeListeners[$key]
            );
        }
    }

    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($interval, $callback, false);

        $this->scheduleTimer($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($interval, $callback, true);

        $this->scheduleTimer($timer);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        if ($this->isTimerActive($timer)) {
            $event = $this->timerEvents[$timer];

            event_del($event);
            event_free($event);

            $this->timerEvents->detach($timer);
        }
    }

    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timerEvents->contains($timer);
    }

    public function futureTick(callable $listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function addSignal($signal, callable $listener)
    {
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

            $flags = EVLOOP_ONCE;
            if (!$this->running || !$this->futureTickQueue->isEmpty()) {
                $flags |= EVLOOP_NONBLOCK;
            } elseif (!$this->streamEvents && !$this->timerEvents->count()) {
                break;
            }

            event_base_loop($this->eventBase, $flags);
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    /**
     * Schedule a timer for execution.
     *
     * @param TimerInterface $timer
     */
    private function scheduleTimer(TimerInterface $timer)
    {
        $this->timerEvents[$timer] = $event = event_timer_new();

        event_timer_set($event, $this->timerCallback, $timer);
        event_base_set($event, $this->eventBase);
        event_add($event, $timer->getInterval() * self::MICROSECONDS_PER_SECOND);
    }

    /**
     * Create a new ext-libevent event resource, or update the existing one.
     *
     * @param resource $stream
     * @param integer  $flag   EV_READ or EV_WRITE
     */
    private function subscribeStreamEvent($stream, $flag)
    {
        $key = (int) $stream;

        if (isset($this->streamEvents[$key])) {
            $event = $this->streamEvents[$key];
            $flags = $this->streamFlags[$key] |= $flag;

            event_del($event);
            event_set($event, $stream, EV_PERSIST | $flags, $this->streamCallback);
        } else {
            $event = event_new();

            event_set($event, $stream, EV_PERSIST | $flag, $this->streamCallback);
            event_base_set($event, $this->eventBase);

            $this->streamEvents[$key] = $event;
            $this->streamFlags[$key] = $flag;
        }

        event_add($event);
    }

    /**
     * Update the ext-libevent event resource for this stream to stop listening to
     * the given event type, or remove it entirely if it's no longer needed.
     *
     * @param resource $stream
     * @param integer  $flag   EV_READ or EV_WRITE
     */
    private function unsubscribeStreamEvent($stream, $flag)
    {
        $key = (int) $stream;

        $flags = $this->streamFlags[$key] &= ~$flag;

        if (0 === $flags) {
            $this->removeStream($stream);

            return;
        }

        $event = $this->streamEvents[$key];

        event_del($event);
        event_set($event, $stream, EV_PERSIST | $flags, $this->streamCallback);
        event_add($event);
    }

    /**
     * Create a callback used as the target of timer events.
     *
     * A reference is kept to the callback for the lifetime of the loop
     * to prevent "Cannot destroy active lambda function" fatal error from
     * the event extension.
     */
    private function createTimerCallback()
    {
        $this->timerCallback = function ($_, $__, $timer) {
            call_user_func($timer->getCallback(), $timer);

            // Timer already cancelled ...
            if (!$this->isTimerActive($timer)) {
                return;

            // Reschedule periodic timers ...
            } elseif ($timer->isPeriodic()) {
                event_add(
                    $this->timerEvents[$timer],
                    $timer->getInterval() * self::MICROSECONDS_PER_SECOND
                );

            // Clean-up one shot timers ...
            } else {
                $this->cancelTimer($timer);
            }
        };
    }

    /**
     * Create a callback used as the target of stream events.
     *
     * A reference is kept to the callback for the lifetime of the loop
     * to prevent "Cannot destroy active lambda function" fatal error from
     * the event extension.
     */
    private function createStreamCallback()
    {
        $this->streamCallback = function ($stream, $flags) {
            $key = (int) $stream;

            if (EV_READ === (EV_READ & $flags) && isset($this->readListeners[$key])) {
                call_user_func($this->readListeners[$key], $stream);
            }

            if (EV_WRITE === (EV_WRITE & $flags) && isset($this->writeListeners[$key])) {
                call_user_func($this->writeListeners[$key], $stream);
            }
        };
    }
}
