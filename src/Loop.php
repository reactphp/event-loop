<?php

namespace React\EventLoop;

/**
 * The `Loop` class exists as a convenient way to get the currently relevant loop
 */
final class Loop
{
    /**
     * @var LoopInterface
     */
    private static $instance;


    /**
     * Returns the event loop.
     * When no loop is set it will it will call the factory to create one.
     *
     * This method always returns an instance implementing `LoopInterface`,
     * the actual event loop implementation is an implementation detail.
     *
     * This method is the preferred way to get the event loop and using
     * Factory::create has been deprecated.
     *
     * @return LoopInterface
     */
    public static function get()
    {
        if (self::$instance instanceof LoopInterface) {
            return self::$instance;
        }

        self::$instance = Factory::create();

        return self::$instance;
    }

    /**
     * Internal undocumented method, behavior might change or throw in the
     * future. Use with cation and at your own risk.
     *
     * @internal
     * @return void
     */
    public static function set(LoopInterface $loop)
    {
        self::$instance = $loop;
    }
}
