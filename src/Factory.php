<?php

namespace React\EventLoop;

/**
 * The `Factory` class exists as a convenient way to pick the best available event loop implementation.
 */
final class Factory
{
    /**
     * Creates a new event loop instance
     *
     * ```php
     * $loop = React\EventLoop\Factory::create();
     * ```
     *
     * This method always returns an instance implementing `LoopInterface`,
     * the actual event loop implementation is an implementation detail.
     *
     * This method should usually only be called once at the beginning of the program.
     *
     * @return LoopInterface
     */
    public static function create()
    {
        // @codeCoverageIgnoreStart
        if (class_exists('libev\EventLoop', false)) {
            return new ExtLibevLoop();
        } elseif (class_exists('EventBase', false)) {
            return new ExtEventLoop();
        } elseif (function_exists('event_base_new') && PHP_VERSION_ID < 70000) {
            // only use ext-libevent on PHP < 7 for now
            return new ExtLibeventLoop();
        }

        return new StreamSelectLoop();
        // @codeCoverageIgnoreEnd
    }
}
