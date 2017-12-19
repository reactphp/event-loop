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
    private $streamEvents = [];
    private $streamFlags = [];
    private $streamRefs = [];
    private $readListeners = [];
    private $writeListeners = [];
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

        if (!isset($this->readListeners[$key])) {
            $this->readListeners[$key] = $listener;
            $this->subscribeStreamEvent($stream, Event::READ);
        }
    }

    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (!isset($this->writeListeners[$key])) {
            $this->writeListeners[$key] = $listener;
            $this->subscribeStreamEvent($stream, Event::WRITE);
        }
    }

    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->readListeners[$key])) {
            unset($this->readListeners[$key]);
            $this->unsubscribeStreamEvent($stream, Event::READ);
        }
    }

    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->writeListeners[$key])) {
            unset($this->writeListeners[$key]);
            $this->unsubscribeStreamEvent($stream, Event::WRITE);
        }
    }

    private function removeStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->streamEvents[$key])) {
            $this->streamEvents[$key]->free();

            unset(
                $this->streamFlags[$key],
                $this->streamEvents[$key],
                $this->readListeners[$key],
                $this->writeListeners[$key],
                $this->streamRefs[$key]
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
            } elseif (!$this->streamEvents && !$this->timerEvents->count()) {
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
     * Create a new ext-event Event object, or update the existing one.
     *
     * @param resource $stream
     * @param integer  $flag   Event::READ or Event::WRITE
     */
    private function subscribeStreamEvent($stream, $flag)
    {
        $key = (int) $stream;

        if (isset($this->streamEvents[$key])) {
            $event = $this->streamEvents[$key];
            $flags = ($this->streamFlags[$key] |= $flag);

            $event->del();
            $event->set($this->eventBase, $stream, Event::PERSIST | $flags, $this->streamCallback);
        } else {
            $event = new Event($this->eventBase, $stream, Event::PERSIST | $flag, $this->streamCallback);

            $this->streamEvents[$key] = $event;
            $this->streamFlags[$key] = $flag;

            // ext-event does not increase refcount on stream resources for PHP 7+
            // manually keep track of stream resource to prevent premature garbage collection
            if (PHP_VERSION_ID >= 70000) {
                $this->streamRefs[$key] = $stream;
            }
        }

        $event->add();
    }

    /**
     * Update the ext-event Event object for this stream to stop listening to
     * the given event type, or remove it entirely if it's no longer needed.
     *
     * @param resource $stream
     * @param integer  $flag   Event::READ or Event::WRITE
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

        $event->del();
        $event->set($this->eventBase, $stream, Event::PERSIST | $flags, $this->streamCallback);
        $event->add();
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
