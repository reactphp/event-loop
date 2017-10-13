<?php

namespace React\EventLoop;

class Factory
{
    public static function create()
    {
        // @codeCoverageIgnoreStart
        if (class_exists('libev\EventLoop', false)) {
            return new LibEvLoop;
        } elseif (class_exists('EventBase', false)) {
            return new ExtEventLoop;
        } elseif (function_exists('event_base_new') && PHP_VERSION_ID < 70000) {
            // only use ext-libevent on PHP < 7 for now
            return new LibEventLoop();
        }

        return new StreamSelectLoop();
        // @codeCoverageIgnoreEnd
    }
}
