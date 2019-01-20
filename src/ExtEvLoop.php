<?php

namespace React\EventLoop;

use Ev;
use EvIo;
use EvLoop;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use SplObjectStorage;

/**
 * An `ext-ev` based event loop.
 *
 * This loop uses the [`ev` PECL extension](https://pecl.php.net/package/ev),
 * that provides an interface to `libev` library.
 *
 * This loop is known to work with PHP 5.4 through PHP 7+.
 *
 * @see http://php.net/manual/en/book.ev.php
 * @see https://bitbucket.org/osmanov/pecl-ev/overview
 */
class ExtEvLoop implements ExtLoopInterface
{
    /**
     * @var EvLoop
     */
    private $loop;

    /**
     * @var FutureTickQueue
     */
    private $futureTickQueue;

    /**
     * @var SplObjectStorage
     */
    private $timers;

    /**
     * @var EvIo[]
     */
    private $readStreams = array();

    /**
     * @var EvIo[]
     */
    private $writeStreams = array();

    /**
     * @var bool[]
     */
    private $allStreams = array();

    /**
     * @var bool
     */
    private $running;

    /**
     * @var SignalsHandler
     */
    private $signals;

    /**
     * @var \EvSignal[]
     */
    private $signalEvents = array();

    private $dereferences = array();
    private $derefTimers = 0;
    private $derefStreams = 0;

    public function __construct()
    {
        $this->loop = new EvLoop();
        $this->futureTickQueue = new FutureTickQueue();
        $this->timers = new SplObjectStorage();
        $this->signals = new SignalsHandler();
    }

    public function addReadStream($stream, $listener)
    {
        $key = (int)$stream;

        if (isset($this->readStreams[$key])) {
            return;
        }

        $callback = $this->getStreamListenerClosure($stream, $listener);
        $event = $this->loop->io($stream, Ev::READ, $callback);
        $this->readStreams[$key] = $event;
        $this->allStreams[$key] = true;
    }

    /**
     * @param resource $stream
     * @param callable $listener
     *
     * @return \Closure
     */
    private function getStreamListenerClosure($stream, $listener)
    {
        return function () use ($stream, $listener) {
            \call_user_func($listener, $stream);
        };
    }

    public function addWriteStream($stream, $listener)
    {
        $key = (int)$stream;

        if (isset($this->writeStreams[$key])) {
            return;
        }

        $callback = $this->getStreamListenerClosure($stream, $listener);
        $event = $this->loop->io($stream, Ev::WRITE, $callback);
        $this->writeStreams[$key] = $event;
        $this->allStreams[$key] = true;
    }

    public function removeReadStream($stream)
    {
        $key = (int)$stream;

        if (!isset($this->readStreams[$key])) {
            return;
        }

        $this->readStreams[$key]->stop();
        unset($this->readStreams[$key], $this->allStreams[$key]);
    }

    public function removeWriteStream($stream)
    {
        $key = (int)$stream;

        if (!isset($this->writeStreams[$key])) {
            return;
        }

        $this->writeStreams[$key]->stop();
        unset($this->writeStreams[$key], $this->allStreams[$key]);
    }

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, false);

        $that = $this;
        $timers = $this->timers;
        $callback = function () use ($timer, $timers, $that) {
            \call_user_func($timer->getCallback(), $timer);

            if ($timers->contains($timer)) {
                $that->cancelTimer($timer);
            }
        };

        $event = $this->loop->timer($timer->getInterval(), 0.0, $callback);
        $this->timers->attach($timer, $event);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, true);

        $callback = function () use ($timer) {
            \call_user_func($timer->getCallback(), $timer);
        };

        $event = $this->loop->timer($interval, $interval, $callback);
        $this->timers->attach($timer, $event);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        if (!isset($this->timers[$timer])) {
            return;
        }

        $event = $this->timers[$timer];
        $event->stop();
        $this->timers->detach($timer);
    }

    public function futureTick($listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function reference($streamOrTimer)
    {
        if ($streamOrTimer instanceof \React\EventLoop\TimerInterface) {
            if (!$this->timers->contains($streamOrTimer)) {
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
            if (!$this->timers->contains($streamOrTimer)) {
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

            $hasPendingCallbacks = !$this->futureTickQueue->isEmpty();
            $hasStreams = \count($this->allStreams) > $this->derefStreams;
            $hasTimers = $this->timers->count() > $this->derefTimers;

            $wasJustStopped = !$this->running;

            $flags = Ev::RUN_ONCE;
            if ($wasJustStopped || $hasPendingCallbacks) {
                $flags |= Ev::RUN_NOWAIT;
            } elseif (!$hasStreams && !$hasTimers) {
                break;
            }

            $this->loop->run($flags);
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    public function __destruct()
    {
        /** @var TimerInterface $timer */
        foreach ($this->timers as $timer) {
            $this->cancelTimer($timer);
        }

        foreach ($this->readStreams as $key => $stream) {
            $this->removeReadStream($key);
        }

        foreach ($this->writeStreams as $key => $stream) {
            $this->removeWriteStream($key);
        }
    }

    public function addSignal($signal, $listener)
    {
        $this->signals->add($signal, $listener);

        if (!isset($this->signalEvents[$signal])) {
            $this->signalEvents[$signal] = $this->loop->signal($signal, function() use ($signal) {
                $this->signals->call($signal);
            });
        }
    }

    public function removeSignal($signal, $listener)
    {
        $this->signals->remove($signal, $listener);

        if (isset($this->signalEvents[$signal])) {
            $this->signalEvents[$signal]->stop();
            unset($this->signalEvents[$signal]);
        }
    }
}
