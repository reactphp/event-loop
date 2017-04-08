<?php

namespace React\EventLoop;

use Ev;
use EvLoop;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Tick\NextTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use SplObjectStorage;

/**
 * @see https://bitbucket.org/osmanov/pecl-ev/overview
 */
class PeclEvLoop implements LoopInterface
{
    private $loop;
    private $nextTickQueue;
    private $futureTickQueue;
    private $timerEvents;
    private $readEvents = [];
    private $writeEvents = [];
    private $running;

    public function __construct()
    {
        $this->loop             = new EvLoop();
        $this->nextTickQueue    = new NextTickQueue($this);
        $this->futureTickQueue  = new FutureTickQueue($this);
        $this->timerEvents      = new SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $callback = function () use ($stream, $listener) {
            call_user_func($listener, $stream, $this);
        };

        $event = $this->loop->io($stream, Ev::READ, $callback);

        $this->readEvents[(int) $stream] = $event;
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $callback = function () use ($stream, $listener) {
            call_user_func($listener, $stream, $this);
        };

        $event = $this->loop->io($stream, Ev::WRITE, $callback);

        $this->writeEvents[(int) $stream] = $event;
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->readEvents[$key])) {
            $this->readEvents[$key]->stop();
            unset($this->readEvents[$key]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        $key = (int) $stream;

        if (isset($this->writeEvents[$key])) {
            $this->writeEvents[$key]->stop();
            unset($this->writeEvents[$key]);
        }
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
        $this->timerEvents->attach($timer, $event);

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
        $this->timerEvents->attach($timer, $event);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timerEvents[$timer])) {
            $event = $this->timerEvents[$timer];
            $event->stop();
            $this->timerEvents->detach($timer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timerEvents->contains($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function nextTick(callable $listener)
    {
        $this->nextTickQueue->add($listener);
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
        $this->nextTickQueue->tick();

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
            $this->nextTickQueue->tick();

            $this->futureTickQueue->tick();

            $flags = Ev::RUN_ONCE;
            if (!$this->running || !$this->nextTickQueue->isEmpty() || !$this->futureTickQueue->isEmpty()) {
                $flags |= Ev::RUN_NOWAIT;
            } elseif (!$this->readEvents && !$this->writeEvents && !$this->timerEvents->count()) {
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
