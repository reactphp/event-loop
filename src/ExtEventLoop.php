<?php

namespace React\EventLoop;

use Event;
use EventBase;
use EventConfig as EventBaseConfig;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\TimerInterface;
use SplObjectStorage;

/**
 * An `ext-event` based event loop.
 *
 * This uses the [`event` PECL extension](https://pecl.php.net/package/event).
 * It supports the same backends as libevent.
 *
 * This loop is known to work with PHP 5.4 through PHP 7+.
 *
 * @link https://pecl.php.net/package/event
 */
final class ExtEventLoop implements LoopInterface
{
    private $eventBase;
    private $futureTickQueue;
    private $timerCallback;
    private $timerEvents;
    private $streamCallback;
    private $readEvents = [];
    private $writeEvents = [];
    private $readListeners = [];
    private $writeListeners = [];
    private $readRefs = [];
    private $writeRefs = [];
    private $running;
    private $signals;
    private $signalEvents = [];

    public function __construct(EventBaseConfig $config = null)
    {
        $this->eventBase = new EventBase($config);
        $this->futureTickQueue = new FutureTickQueue();
        $this->timerEvents = new SplObjectStorage();

        $this->signals = new SignalsHandler(
            $this,
            function ($signal) {
                $this->signalEvents[$signal] = Event::signal($this->eventBase, $signal, $f = function () use ($signal, &$f) {
                    $this->signals->call($signal);
                    // Ensure there are two copies of the callable around until it has been executed.
                    // For more information see: https://bugs.php.net/bug.php?id=62452
                    // Only an issue for PHP 5, this hack can be removed once PHP 5 support has been dropped.
                    $g = $f;
                    $f = $g;
                });
                $this->signalEvents[$signal]->add();
            },
            function ($signal) {
                if ($this->signals->count($signal) === 0) {
                    $this->signalEvents[$signal]->del();
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
        if (isset($this->readListeners[$key])) {
            return;
        }

        $event = new Event($this->eventBase, $stream, Event::PERSIST | Event::READ, $this->streamCallback);
        $event->add();
        $this->readEvents[$key] = $event;
        $this->readListeners[$key] = $listener;

        // ext-event does not increase refcount on stream resources for PHP 7+
        // manually keep track of stream resource to prevent premature garbage collection
        if (PHP_VERSION_ID >= 70000) {
            $this->readRefs[$key] = $stream;
        }
    }

    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;
        if (isset($this->writeListeners[$key])) {
            return;
        }

        $event = new Event($this->eventBase, $stream, Event::PERSIST | Event::WRITE, $this->streamCallback);
        $event->add();
        $this->writeEvents[$key] = $event;
        $this->writeListeners[$key] = $listener;

        // ext-event does not increase refcount on stream resources for PHP 7+
        // manually keep track of stream resource to prevent premature garbage collection
        if (PHP_VERSION_ID >= 70000) {
            $this->writeRefs[$key] = $stream;
        }
    }

    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->readEvents[$key])) {
            $this->readEvents[$key]->free();
            unset(
                $this->readEvents[$key],
                $this->readListeners[$key],
                $this->readRefs[$key]
            );
        }
    }

    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->writeEvents[$key])) {
            $this->writeEvents[$key]->free();
            unset(
                $this->writeEvents[$key],
                $this->writeListeners[$key],
                $this->writeRefs[$key]
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
        if ($this->timerEvents->contains($timer)) {
            $this->timerEvents[$timer]->free();
            $this->timerEvents->detach($timer);
        }
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

            $flags = EventBase::LOOP_ONCE;
            if (!$this->running || !$this->futureTickQueue->isEmpty()) {
                $flags |= EventBase::LOOP_NONBLOCK;
            } elseif (!$this->readEvents && !$this->writeEvents && !$this->timerEvents->count()) {
                break;
            }

            $this->eventBase->loop($flags);
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
        $flags = Event::TIMEOUT;

        if ($timer->isPeriodic()) {
            $flags |= Event::PERSIST;
        }

        $event = new Event($this->eventBase, -1, $flags, $this->timerCallback, $timer);
        $this->timerEvents[$timer] = $event;

        $event->add($timer->getInterval());
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

            if (!$timer->isPeriodic() && $this->timerEvents->contains($timer)) {
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

            if (Event::READ === (Event::READ & $flags) && isset($this->readListeners[$key])) {
                call_user_func($this->readListeners[$key], $stream);
            }

            if (Event::WRITE === (Event::WRITE & $flags) && isset($this->writeListeners[$key])) {
                call_user_func($this->writeListeners[$key], $stream);
            }
        };
    }
}
