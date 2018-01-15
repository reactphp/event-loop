<?php

namespace React\EventLoop;

/**
 * @internal
 */
final class SignalsHandler
{
    private $loop;
    private $timer;
    private $signals = [];
    private $on;

    public function __construct(LoopInterface $loop, $on)
    {
        $this->loop = $loop;
        $this->on = $on;
    }

    public function add($signal, $listener)
    {
        if (empty($this->signals) && $this->timer === null) {
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

    public function remove($signal, $listener)
    {
        if (!isset($this->signals[$signal])) {
            return;
        }

        $index = \array_search($listener, $this->signals[$signal], true);
        unset($this->signals[$signal][$index]);

        if (isset($this->signals[$signal]) && \count($this->signals[$signal]) === 0) {
            unset($this->signals[$signal]);
        }

        if (empty($this->signals) && $this->timer instanceof TimerInterface) {
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
