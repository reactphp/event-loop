<?php

namespace React\EventLoop;

use SplObjectStorage;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Tick\NextTickQueue;

class LibUvLoop implements LoopInterface
{
    private $loop;
    private $events = array();
    private $timers;
    private $running = true;
    private $listeners = array();
    private $nextTickQueue;
    private $futureTickQueue;

    public function __construct()
    {
        $this->loop = uv_loop_new();
        $this->timers = new SplObjectStorage();
        $this->nextTickQueue = new NextTickQueue($this);
        $this->futureTickQueue = new FutureTickQueue($this);
    }

    /**
     * {@inheritdoc}
     */
    public function addReadStream($stream, callable $listener)
    {
        $this->addStream($stream, $listener, \UV::READABLE);
    }

    /**
     * {@inheritdoc}
     */
    public function addWriteStream($stream, callable $listener)
    {
        $this->addStream($stream, $listener, \UV::WRITABLE);
    }

    /**
     * {@inheritdoc}
     */
    public function removeReadStream($stream)
    {
        if (!isset($this->events[(int) $stream])) {
            return;
        }

        uv_poll_stop($this->events[(int) $stream]);
        unset($this->listeners[(int) $stream]['read']);

        if (!isset($this->listeners[(int) $stream]['read'])
            && !isset($this->listeners[(int) $stream]['write'])) {
            unset($this->events[(int) $stream]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeWriteStream($stream)
    {
        if (!isset($this->events[(int) $stream])) {
            return;
        }

        uv_poll_stop($this->events[(int) $stream]);
        unset($this->listeners[(int) $stream]['write']);

        if (!isset($this->listeners[(int) $stream]['read'])
            && !isset($this->listeners[(int) $stream]['write'])) {
            unset($this->events[(int) $stream]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeStream($stream)
    {
        if (isset($this->events[(int) $stream])) {

            uv_poll_stop($this->events[(int) $stream]);

            unset($this->listeners[(int) $stream]['read']);
            unset($this->listeners[(int) $stream]['write']);
            unset($this->events[(int) $stream]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTimer($interval, callable $callback)
    {
        return $this->createTimer($interval, $callback, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        return $this->createTimer($interval, $callback, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        uv_timer_stop($this->timers[$timer]);
        uv_unref($this->timers[$timer]);
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

        uv_run($this->loop, \UV::RUN_ONCE);
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

            $flags = \UV::RUN_ONCE;
            if (!$this->running || !$this->nextTickQueue->isEmpty() || !$this->futureTickQueue->isEmpty()) {
                $flags = \UV::RUN_NOWAIT;
            } elseif (empty($this->events) && !$this->timers->count()) {
                break;
            }

            uv_run($this->loop, $flags);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }

    /* PRIVATE */

    private function addStream($stream, $listener, $flags)
    {
        $meta = stream_get_meta_data($stream);
        if (get_resource_type($stream) == "Unknown" || !(strpos($meta['stream_type'], 'socket')) ) {
            throw new \InvalidArgumentException("Stream must be a resource of type socket.");

            return false;
        }

        $currentFlag = 0;
        if (isset($this->listeners[(int) $stream]['read'])) {
            $currentFlag |= \UV::READABLE;
        }

        if (isset($this->listeners[(int) $stream]['write'])) {
            $currentFlag |= \UV::WRITABLE;
        }

        if (($flags & \UV::READABLE) === $flags) {
            $this->listeners[(int) $stream]['read'] = $listener;
        } elseif (($flags & \UV::WRITABLE) === $flags) {
            $this->listeners[(int) $stream]['write'] = $listener;
        }

        if (!isset($this->events[(int) $stream])) {
            $event = uv_poll_init($this->loop, $stream);
            $this->events[(int) $stream] = $event;
        } else {
            $event = $this->events[(int) $stream];
        }

        $listener = $this->createStreamListener();
        uv_poll_start($event, $currentFlag | $flags, $listener);
    }

    /**
     * Create a stream listener
     *
     * @return callable Returns a callback
     */
    private function createStreamListener()
    {
        $loop = $this;

        $callback = function ($poll, $status, $event, $stream) use ($loop, &$callback) {
            if ($status < 0) {

                if (isset($loop->listeners[(int) $stream]['read'])) {
                    call_user_func(array($this, 'removeReadStream'), $stream);
                }

                if (isset($loop->writeListeners[(int) $stream]['write'])) {
                    call_user_func(array($this, 'removeWriteStream'), $stream);
                }

                return;
            }

            if (($event & \UV::READABLE) && isset($loop->listeners[(int) $stream]['read'])) {
                call_user_func($loop->listeners[(int) $stream]['read'], $stream);
            }

            if (($event & \UV::WRITABLE) && isset($loop->listeners[(int) $stream]['write'])) {
                call_user_func($loop->listeners[(int) $stream]['write'], $stream);
            }
        };

        return $callback;
    }

    /**
     * Add callback and configured a timer
     *
     * @param  Int          $interval The interval of the timer
     * @param  Callable     $callback The callback to be executed
     * @param  int          $periodic 0 = one-off, 1 = periodic
     * @return Timer        Returns a timer instance
     */
    private function createTimer($interval, $callback, $periodic)
    {
        $timer = new Timer($this, $interval, $callback, $periodic);
        $resource = uv_timer_init($this->loop);

        $timers = $this->timers;
        $timers->attach($timer, $resource);

        $callback = $this->wrapTimerCallback($timer, $periodic);
        uv_timer_start($resource, $interval * 1000, $interval * 1000, $callback);

        return $timer;
    }

    /**
     * Create a timer wrapper for periodic/one-off timers
     *
     * @param  Timer        $timer      Timer object
     * @param  int          $periodic   0 = one-off, 1 = periodic
     * @return Callable                 wrapper
     */
    private function wrapTimerCallback($timer, $periodic)
    {
        $callback = function () use ($timer, $periodic) {

            call_user_func($timer->getCallback(), $timer);

            if (!$periodic) {
                $timer->cancel();
            }
        };

        return $callback;
    }
}
