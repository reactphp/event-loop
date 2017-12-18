<?php

namespace React\EventLoop;

use React\EventLoop\TimerInterface;

/**
 * @internal
 */
final class SignalsHandler
{
    private $loop;
    private $timer;
    private $signals = [];
    private $on;
    private $off;

    public function __construct(LoopInterface $loop, callable $on, callable $off)
    {
        $this->loop = $loop;
        $this->on = $on;
        $this->off = $off;
    }

    public function __destruct()
    {
        $off = $this->off;
        foreach ($this->signals as $signal => $listeners) {
            $off($signal);
        }
    }

    public function add($signal, callable $listener)
    {
        if (count($this->signals) == 0 && $this->timer === null) {
            /**
             * Timer to keep the loop alive as long as there are any signal handlers registered
             */
            $this->timer = $this->loop->addPeriodicTimer(300, function () {});
        }

        if (!isset($this->signals[$signal])) {
            $this->signals[$signal] = [];

            $on = $this->on;
            $on($signal);
        }

        if (in_array($listener, $this->signals[$signal])) {
            return;
        }

        $this->signals[$signal][] = $listener;
    }

    public function remove($signal, callable $listener)
    {
        if (!isset($this->signals[$signal])) {
            return;
        }

        $index = \array_search($listener, $this->signals[$signal], true);
        unset($this->signals[$signal][$index]);

        if (isset($this->signals[$signal]) && \count($this->signals[$signal]) === 0) {
            unset($this->signals[$signal]);

            $off = $this->off;
            $off($signal);
        }

        if (count($this->signals) == 0 && $this->timer instanceof TimerInterface) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
    }

    public function call($signal)
    {
        if (!isset($this->signals[$signal])) {
            return;
        }

        foreach ($this->signals[$signal] as $listener) {
            \call_user_func($listener, $signal);
        }
    }

    public function count($signal)
    {
        if (!isset($this->signals[$signal])) {
            return 0;
        }

        return \count($this->signals[$signal]);
    }
}
