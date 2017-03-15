<?php

namespace React\EventLoop;

class Factory
{
    public static function create()
    {
        // @codeCoverageIgnoreStart
        if (class_exists('Ev', false)) {
          return new ExtEvLoop;
        } elseif (function_exists('event_base_new')) {
            return new LibEventLoop();
        } elseif (class_exists('libev\EventLoop', false)) {
            return new LibEvLoop;
        } elseif (class_exists('EventBase', false)) {
            return new ExtEventLoop;
        }

        return new StreamSelectLoop();
        // @codeCoverageIgnoreEnd
    }
}
