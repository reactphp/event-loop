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
 * `libev` itself supports a number of system-specific backends (epoll, kqueue).
 *
 * This loop is known to work with PHP 7.1 through PHP 8+.
 *
 * @see http://php.net/manual/en/book.ev.php
 * @see https://bitbucket.org/osmanov/pecl-ev/overview
 */
class ExtEvLoop implements LoopInterface
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
    private $readStreams = [];

    /**
     * @var EvIo[]
     */
    private $writeStreams = [];

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
    private $signalEvents = [];

    public function __construct()
    {
        $this->loop = new EvLoop();
        $this->futureTickQueue = new FutureTickQueue();
        $this->timers = new SplObjectStorage();
        $this->signals = new SignalsHandler();
    }

    public function addReadStream($stream, callable $listener): void
    {
        $key = (int)$stream;

        if (isset($this->readStreams[$key])) {
            return;
        }

        $callback = $this->getStreamListenerClosure($stream, $listener);
        $event = $this->loop->io($stream, Ev::READ, $callback);
        $this->readStreams[$key] = $event;
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

    public function addWriteStream($stream, callable $listener): void
    {
        $key = (int)$stream;

        if (isset($this->writeStreams[$key])) {
            return;
        }

        $callback = $this->getStreamListenerClosure($stream, $listener);
        $event = $this->loop->io($stream, Ev::WRITE, $callback);
        $this->writeStreams[$key] = $event;
    }

    public function removeReadStream($stream): void
    {
        $key = (int)$stream;

        if (!isset($this->readStreams[$key])) {
            return;
        }

        $this->readStreams[$key]->stop();
        unset($this->readStreams[$key]);
    }

    public function removeWriteStream($stream): void
    {
        $key = (int)$stream;

        if (!isset($this->writeStreams[$key])) {
            return;
        }

        $this->writeStreams[$key]->stop();
        unset($this->writeStreams[$key]);
    }

    public function addTimer(float $interval, callable $callback): TimerInterface
    {
        $timer = new Timer($interval, $callback, false);

        $callback = function () use ($timer) {
            \call_user_func($timer->getCallback(), $timer);

            if ($this->timers->offsetExists($timer)) {
                $this->cancelTimer($timer);
            }
        };

        $event = $this->loop->timer($timer->getInterval(), 0.0, $callback);
        $this->timers->offsetSet($timer, $event);

        return $timer;
    }

    public function addPeriodicTimer(float $interval, callable $callback): TimerInterface
    {
        $timer = new Timer($interval, $callback, true);

        $callback = function () use ($timer) {
            \call_user_func($timer->getCallback(), $timer);
        };

        $event = $this->loop->timer($timer->getInterval(), $timer->getInterval(), $callback);
        $this->timers->offsetSet($timer, $event);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer): void
    {
        if (!isset($this->timers[$timer])) {
            return;
        }

        $event = $this->timers[$timer];
        $event->stop();
        $this->timers->offsetUnset($timer);
    }

    public function futureTick(callable $listener): void
    {
        $this->futureTickQueue->add($listener);
    }

    public function run(): void
    {
        $this->running = true;

        while ($this->running) {
            $this->futureTickQueue->tick();

            $hasPendingCallbacks = !$this->futureTickQueue->isEmpty();
            $wasJustStopped = !$this->running;
            $nothingLeftToDo = !$this->readStreams
                && !$this->writeStreams
                && !$this->timers->count()
                && $this->signals->isEmpty();

            $flags = Ev::RUN_ONCE;
            if ($wasJustStopped || $hasPendingCallbacks) {
                $flags |= Ev::RUN_NOWAIT;
            } elseif ($nothingLeftToDo) {
                break;
            }

            $this->loop->run($flags);
        }
    }

    public function stop(): void
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

    public function addSignal(int $signal, callable $listener): void
    {
        $this->signals->add($signal, $listener);

        if (!isset($this->signalEvents[$signal])) {
            $this->signalEvents[$signal] = $this->loop->signal($signal, function() use ($signal) {
                $this->signals->call($signal);
            });
        }
    }

    public function removeSignal(int $signal, callable $listener): void
    {
        $this->signals->remove($signal, $listener);

        if (isset($this->signalEvents[$signal])) {
            $this->signalEvents[$signal]->stop();
            unset($this->signalEvents[$signal]);
        }
    }
}
