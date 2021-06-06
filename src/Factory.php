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
     * @deprecated Use Loop::get instead
     *
     * @return LoopInterface
     */
    public static function create()
    {
        $loop = self::construct();

        Loop::set($loop);

        return $loop;
    }

    /**
     * @internal
     * @return LoopInterface
     */
    private static function construct()
    {
        // @codeCoverageIgnoreStart
        if (\function_exists('uv_loop_new')) {
            // only use ext-uv on PHP 7
            return new ExtUvLoop();
        }

        if (\class_exists('libev\EventLoop', false)) {
            return new ExtLibevLoop();
        }

        if (\class_exists('EvLoop', false)) {
            return new ExtEvLoop();
        }

        if (\class_exists('EventBase', false)) {
            return new ExtEventLoop();
        }

        if (\function_exists('event_base_new') && \PHP_MAJOR_VERSION === 5) {
            // only use ext-libevent on PHP 5 for now
            return new ExtLibeventLoop();
        }

        return new StreamSelectLoop();
        // @codeCoverageIgnoreEnd
    }
}
