<?php

namespace React\EventLoop;

use BadMethodCallException;
use Event;
use EventBase;
use EventConfig as EventBaseConfig;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
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
final class ExtEventLoop implements ExtLoopInterface
{
    private $eventBase;
    private $futureTickQueue;
    private $timerCallback;
    private $timerEvents;
    private $streamCallback;
    private $readEvents = array();
    private $writeEvents = array();
    private $readListeners = array();
    private $writeListeners = array();
    private $allStreams = array();
    private $readRefs = array();
    private $writeRefs = array();
    private $running;
    private $signals;
    private $signalEvents = array();

    private $dereferences = array();
    private $derefTimers = 0;
    private $derefStreams = 0;

    public function __construct()
    {
        if (!\class_exists('EventBase', false)) {
            throw new BadMethodCallException('Cannot create ExtEventLoop, ext-event extension missing');
        }

        $config = new EventBaseConfig();
        $config->requireFeatures(EventBaseConfig::FEATURE_FDS);

        $this->eventBase = new EventBase($config);
        $this->futureTickQueue = new FutureTickQueue();
        $this->timerEvents = new SplObjectStorage();
        $this->signals = new SignalsHandler();

        $this->createTimerCallback();
        $this->createStreamCallback();
    }

    public function addReadStream($stream, $listener)
    {
        $key = (int) $stream;
        if (isset($this->readListeners[$key])) {
            return;
        }

        $event = new Event($this->eventBase, $stream, Event::PERSIST | Event::READ, $this->streamCallback);
        $event->add();
        $this->readEvents[$key] = $event;
        $this->readListeners[$key] = $listener;
        $this->allStreams[$key] = true;

        // ext-event does not increase refcount on stream resources for PHP 7+
        // manually keep track of stream resource to prevent premature garbage collection
        if (\PHP_VERSION_ID >= 70000) {
            $this->readRefs[$key] = $stream;
        }
    }

    public function addWriteStream($stream, $listener)
    {
        $key = (int) $stream;
        if (isset($this->writeListeners[$key])) {
            return;
        }

        $event = new Event($this->eventBase, $stream, Event::PERSIST | Event::WRITE, $this->streamCallback);
        $event->add();
        $this->writeEvents[$key] = $event;
        $this->writeListeners[$key] = $listener;
        $this->allStreams[$key] = true;

        // ext-event does not increase refcount on stream resources for PHP 7+
        // manually keep track of stream resource to prevent premature garbage collection
        if (\PHP_VERSION_ID >= 70000) {
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
                $this->readRefs[$key],
                $this->allStreams[$key]
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
                $this->writeRefs[$key],
                $this->allStreams[$key]
            );
        }
    }

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, false);

        $this->scheduleTimer($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
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

    public function futureTick($listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function addSignal($signal, $listener)
    {
        $this->signals->add($signal, $listener);

        if (!isset($this->signalEvents[$signal])) {
            $this->signalEvents[$signal] = Event::signal($this->eventBase, $signal, array($this->signals, 'call'));
            $this->signalEvents[$signal]->add();
        }
    }

    public function removeSignal($signal, $listener)
    {
        $this->signals->remove($signal, $listener);

        if (isset($this->signalEvents[$signal]) && $this->signals->count($signal) === 0) {
            $this->signalEvents[$signal]->free();
            unset($this->signalEvents[$signal]);
        }
    }

    public function reference($streamOrTimer)
    {
        if ($streamOrTimer instanceof \React\EventLoop\TimerInterface) {
            if (!$this->timerEvents->contains($streamOrTimer)) {
                throw new \InvalidArgumentException('Given timer is not part of this loop');
            }

            $key = \spl_object_hash($streamOrTimer);

            if (isset($this->dereferences[$key])) {
                unset($this->dereferences[$key]);
                $this->derefTimers--;
            }
        } else {
            $key = (int) $streamOrTimer;

            if (!isset($this->allStreams[$key])) {
                throw new \InvalidArgumentException('Given stream is not part of a read or write session of this loop');
            }
            
            if (isset($this->dereferences[$key])) {
                unset($this->dereferences[$key]);
                $this->derefStreams--;
            }
        }
    }
    
    public function dereference($streamOrTimer)
    {
        if ($streamOrTimer instanceof \React\EventLoop\TimerInterface) {
            if (!$this->timerEvents->contains($streamOrTimer)) {
                throw new \InvalidArgumentException('Given timer is not part of this loop');
            }

            $key = \spl_object_hash($streamOrTimer);

            if (!isset($this->dereferences[$key])) {
                $this->dereferences[$key] = true;
                $this->derefTimers++;
            }
        } else {
            $key = (int) $streamOrTimer;

            if (!isset($this->allStreams[$key])) {
                throw new \InvalidArgumentException('Given stream is not part of a read or write session of this loop');
            }

            if (!isset($this->dereferences[$key])) {
                $this->dereferences[$key] = true;
                $this->derefStreams++;
            }
        }
    }

    public function run()
    {
        $this->running = true;

        while ($this->running) {
            $this->futureTickQueue->tick();

            $hasStreams = \count($this->allStreams) > $this->derefStreams;
            $hasTimers = $this->timerEvents->count() > $this->derefTimers;

            $flags = EventBase::LOOP_ONCE;
            if (!$this->running || !$this->futureTickQueue->isEmpty()) {
                $flags |= EventBase::LOOP_NONBLOCK;
            } elseif (!$hasStreams && !$hasTimers && $this->signals->isEmpty()) {
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
        $timers = $this->timerEvents;
        $this->timerCallback = function ($_, $__, $timer) use ($timers) {
            \call_user_func($timer->getCallback(), $timer);

            if (!$timer->isPeriodic() && $timers->contains($timer)) {
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
        $read =& $this->readListeners;
        $write =& $this->writeListeners;
        $this->streamCallback = function ($stream, $flags) use (&$read, &$write) {
            $key = (int) $stream;

            if (Event::READ === (Event::READ & $flags) && isset($read[$key])) {
                \call_user_func($read[$key], $stream);
            }

            if (Event::WRITE === (Event::WRITE & $flags) && isset($write[$key])) {
                \call_user_func($write[$key], $stream);
            }
        };
    }
}
