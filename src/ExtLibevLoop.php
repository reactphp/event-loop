<?php

namespace React\EventLoop;

use libev\EventLoop;
use libev\IOEvent;
use libev\SignalEvent;
use libev\TimerEvent;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use SplObjectStorage;

/**
 * An `ext-libev` based event loop.
 *
 * This uses an [unofficial `libev` extension](https://github.com/m4rw3r/php-libev).
 * It supports the same backends as libevent.
 *
 * This loop does only work with PHP 5.
 * An update for PHP 7 is [unlikely](https://github.com/m4rw3r/php-libev/issues/8)
 * to happen any time soon.
 *
 * @see https://github.com/m4rw3r/php-libev
 * @see https://gist.github.com/1688204
 */
class ExtLibevLoop implements LoopInterface
{
    private $loop;
    private $futureTickQueue;
    private $timerEvents;
    private $readEvents = [];
    private $writeEvents = [];
    private $running;
    private $signals;
    private $signalEvents = [];

    public function __construct()
    {
        $this->loop = new EventLoop();
        $this->futureTickQueue = new FutureTickQueue();
        $this->timerEvents = new SplObjectStorage();

        $this->signals = new SignalsHandler(
            $this,
            function ($signal) {
                $this->signalEvents[$signal] = new SignalEvent($f = function () use ($signal, &$f) {
                    $this->signals->call($signal);
                    // Ensure there are two copies of the callable around until it has been executed.
                    // For more information see: https://bugs.php.net/bug.php?id=62452
                    // Only an issue for PHP 5, this hack can be removed once PHP 5 support has been dropped.
                    $g = $f;
                    $f = $g;
                }, $signal);
                $this->loop->add($this->signalEvents[$signal]);
            },
            function ($signal) {
                if ($this->signals->count($signal) === 0) {
                    $this->loop->remove($this->signalEvents[$signal]);
                    unset($this->signalEvents[$signal]);
                }
            }
        );
    }

    public function addReadStream($stream, callable $listener)
    {
        if (isset($this->readEvents[(int) $stream])) {
            return;
        }

        $callback = function () use ($stream, $listener) {
            call_user_func($listener, $stream);
        };

        $event = new IOEvent($callback, $stream, IOEvent::READ);
        $this->loop->add($event);

        $this->readEvents[(int) $stream] = $event;
    }

    public function addWriteStream($stream, callable $listener)
    {
        if (isset($this->writeEvents[(int) $stream])) {
            return;
        }

        $callback = function () use ($stream, $listener) {
            call_user_func($listener, $stream);
        };

        $event = new IOEvent($callback, $stream, IOEvent::WRITE);
        $this->loop->add($event);

        $this->writeEvents[(int) $stream] = $event;
    }

    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->readEvents[$key])) {
            $this->readEvents[$key]->stop();
            unset($this->readEvents[$key]);
        }
    }

    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->writeEvents[$key])) {
            $this->writeEvents[$key]->stop();
            unset($this->writeEvents[$key]);
        }
    }

    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer( $interval, $callback, false);

        $callback = function () use ($timer) {
            call_user_func($timer->getCallback(), $timer);

            if ($this->timerEvents->contains($timer)) {
                $this->cancelTimer($timer);
            }
        };

        $event = new TimerEvent($callback, $timer->getInterval());
        $this->timerEvents->attach($timer, $event);
        $this->loop->add($event);

        return $timer;
    }

    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($interval, $callback, true);

        $callback = function () use ($timer) {
            call_user_func($timer->getCallback(), $timer);
        };

        $event = new TimerEvent($callback, $interval, $interval);
        $this->timerEvents->attach($timer, $event);
        $this->loop->add($event);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timerEvents[$timer])) {
            $this->loop->remove($this->timerEvents[$timer]);
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

            $flags = EventLoop::RUN_ONCE;
            if (!$this->running || !$this->futureTickQueue->isEmpty()) {
                $flags |= EventLoop::RUN_NOWAIT;
            } elseif (!$this->readEvents && !$this->writeEvents && !$this->timerEvents->count()) {
                break;
            }

            $this->loop->run($flags);
        }
    }

    public function stop()
    {
        $this->running = false;
    }
}
