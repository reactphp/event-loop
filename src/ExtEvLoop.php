<?php

namespace React\EventLoop;

use SplObjectStorage;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Tick\NextTickQueue;

class ExtEvLoop implements LoopInterface
{
    private $loop;
    private $nextTickQueue;
    private $futureTickQueue;
    private $timers;
    private $readEvents     = array();
    private $writeEvents    = array();

    private $running        = false;

    public function __construct()
    {
        $this->loop             = new \EvLoop();
        $this->timers           = new SplObjectStorage();
        $this->nextTickQueue    = new NextTickQueue($this);
        $this->futureTickQueue  = new FutureTickQueue($this);
        $this->timers           = new SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $this->addStream($stream, $listener, \Ev::READ);
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $this->addStream($stream, $listener, \Ev::WRITE);
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
     * Wraps the listener in a callback which will pass the
     * stream to the listener then registers the stream with
     * the eventloop.
     *
     * @param resource    $stream   PHP Stream resource
     * @param callable    $listener stream callback
     * @param int         $flags    flag bitmask
     */
    private function addStream($stream, callable $listener, $flags)
    {
        $listener = function ($event) use ($stream, $listener) {
            call_user_func($listener, $stream, $this);
        };

        $event = $this->loop->io($stream, $flags, $listener);

        if (($flags & \Ev::READ) === $flags) {
            $this->readEvents[(int)$stream] = $event;
        } elseif (($flags & \Ev::WRITE) === $flags) {
            $this->writeEvents[(int)$stream] = $event;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, false);
        $this->setupTimer($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = new Timer($this, $interval, $callback, true);
        $this->setupTimer($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            /* stop EvTimer */
            $this->timers[$timer]->stop();

            /* defer timer */
            $this->nextTick(function() use ($timer) {
                $this->timers->detach($timer);
            });
        }
    }

    /**
     * Add timer object as
     * @param  TimerInterface $timer [description]
     * @return [type]                [description]
     */
    private function setupTimer(TimerInterface $timer)
    {
        $callback = function () use ($timer) {
            call_user_func($timer->getCallback(), $timer);

            if (!$timer->isPeriodic()) {
                $timer->cancel();
            }
        };

        $interval = $timer->getInterval();

        $libevTimer = $this->loop->timer($interval, $interval, $callback);

        $this->timers->attach($timer, $libevTimer);

        return $timer;
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

        $flags = \Ev::RUN_ONCE;
        if (!$this->running || !$this->nextTickQueue->isEmpty() || !$this->futureTickQueue->isEmpty()) {
            $flags |= \Ev::RUN_NOWAIT;
        } elseif (!$this->readEvents && !$this->writeEvents && !$this->timers->count()) {
            $this->running = false;
            return;
        }
        $this->loop->run($flags);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->running = true;

        while($this->running) {
            $this->tick();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }

    public function __destruct()
    {
        // mannually stop all watchers
        foreach ($this->timers as $timer) {
            $this->timers[$timer]->stop();
        }

        foreach ($this->readEvents as $event) {
            $event->stop();
        }

        foreach ($this->writeEvents as $event) {
            $event->stop();
        }
    }
}
