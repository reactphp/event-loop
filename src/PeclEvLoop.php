<?php

namespace React\EventLoop;

use Ev;
use EvLoop;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use SplObjectStorage;

/**
 * @see https://bitbucket.org/osmanov/pecl-ev/overview
 */
class PeclEvLoop implements LoopInterface
{
    private $loop;
    private $futureTickQueue;
    private $timers;
    private $readStreams = [];
    private $writeStreams = [];
    private $running;

    public function __construct()
    {
        $this->loop             = new EvLoop();
        $this->futureTickQueue  = new FutureTickQueue($this);
        $this->timers           = new SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $key = (int) $stream;

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
    private function getStreamListenerClosure($stream, callable $listener) {
        return function () use ($stream, $listener) {
            call_user_func($listener, $stream, $this);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $key = (int) $stream;

        if (isset($this->writeStreams[$key])) {
            return;
        }

        $callback = $this->getStreamListenerClosure($stream, $listener);
        $event = $this->loop->io($stream, Ev::WRITE, $callback);
        $this->writeStreams[$key] = $event;
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (!isset($this->readStreams[$key])) {
            return;
        }

        $this->readStreams[$key]->stop();
        unset($this->readStreams[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (!isset($this->writeStreams[$key])) {
            return;
        }

        $this->writeStreams[$key]->stop();
        unset($this->writeStreams[$key]);
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

        $callback = function () use ($timer) {
            call_user_func($timer->getCallback(), $timer);

            if ($this->isTimerActive($timer)) {
                $this->cancelTimer($timer);
            }
        };

        $event = $this->loop->timer($timer->getInterval(), 0.0, $callback);
        $this->timers->attach($timer, $event);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);

        $callback = function () use ($timer) {
            call_user_func($timer->getCallback(), $timer);
        };

        //reschedule callback should be NULL to utilize $offset and $interval params
        $event = $this->loop->periodic($interval, $interval, NULL, $callback);
        $this->timers->attach($timer, $event);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if (!isset($this->timers[$timer])) {
            return;
        }

        $event = $this->timers[$timer];
        $event->stop();
        $this->timers->detach($timer);
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
    public function tick()
    {
        $this->futureTickQueue->tick();

        $this->loop->run(Ev::RUN_ONCE | Ev::RUN_NOWAIT);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->running = true;

        while ($this->running) {
            $this->futureTickQueue->tick();

            $hasPendingCallbacks = !$this->futureTickQueue->isEmpty();
            $wasJustStopped = !$this->running;
            $nothingLeftToDo = !$this->readStreams && !$this->writeStreams && !$this->timers->count();

            $flags = Ev::RUN_ONCE;
            if ($wasJustStopped || $hasPendingCallbacks) {
                $flags |= Ev::RUN_NOWAIT;
            } elseif ($nothingLeftToDo) {
                break;
            }

            $this->loop->run($flags);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }
}
