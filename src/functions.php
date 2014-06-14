<?php

namespace React\EventLoop;

function create()
{
    // @codeCoverageIgnoreStart
    if (function_exists('event_base_new')) {
        return new LibEventLoop();
    } else if (class_exists('libev\EventLoop', false)) {
        return new LibEvLoop;
    } else if (class_exists('EventBase', false)) {
        return new ExtEventLoop;
    }

    return new StreamSelectLoop();
    // @codeCoverageIgnoreEnd
}

function loop() {
    if (!State::$loop) {
        State::$loop = create();
    }
    return State::$loop;
}

function register(LoopInterface $loop)
{
    State::$loop = $loop;
}
