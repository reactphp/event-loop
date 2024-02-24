<?php

namespace React\EventLoop;

/**
 * The `Loop` class exists as a convenient way to get the currently relevant loop
 */
final class Loop
{
    /**
     * @var ?LoopInterface
     */
    private static $instance;

    /** @var bool */
    private static $stopped = false;

    /**
     * Returns the event loop.
     * When no loop is set, it will call the factory to create one.
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

        self::$instance = $loop = self::create();

        // Automatically run loop at end of program, unless already started or stopped explicitly.
        // This is tested using child processes, so coverage is actually 100%, see BinTest.
        // @codeCoverageIgnoreStart
        $hasRun = false;
        $loop->futureTick(function () use (&$hasRun) {
            $hasRun = true;
        });

        $stopped =& self::$stopped;
        register_shutdown_function(function () use ($loop, &$hasRun, &$stopped) {
            // Don't run if we're coming from a fatal error (uncaught exception).
            $error = error_get_last();
            if ((isset($error['type']) ? $error['type'] : 0) & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
                return;
            }

            if (!$hasRun && !$stopped) {
                $loop->run();
            }
        });
        // @codeCoverageIgnoreEnd

        return self::$instance;
    }

    /**
     * Internal undocumented method, behavior might change or throw in the
     * future. Use with caution and at your own risk.
     *
     * @internal
     * @return void
     */
    public static function set(LoopInterface $loop)
    {
        self::$instance = $loop;
    }

    /**
     * [Advanced] Register a listener to be notified when a stream is ready to read.
     *
     * @param resource $stream
     * @param callable $listener
     * @return void
     * @throws \Exception
     * @see LoopInterface::addReadStream()
     */
    public static function addReadStream($stream, $listener)
    {
        (self::$instance ?? self::get())->addReadStream($stream, $listener);
    }

    /**
     * [Advanced] Register a listener to be notified when a stream is ready to write.
     *
     * @param resource $stream
     * @param callable $listener
     * @return void
     * @throws \Exception
     * @see LoopInterface::addWriteStream()
     */
    public static function addWriteStream($stream, $listener)
    {
        (self::$instance ?? self::get())->addWriteStream($stream, $listener);
    }

    /**
     * Remove the read event listener for the given stream.
     *
     * @param resource $stream
     * @return void
     * @see LoopInterface::removeReadStream()
     */
    public static function removeReadStream($stream)
    {
        if (self::$instance !== null) {
            self::$instance->removeReadStream($stream);
        }
    }

    /**
     * Remove the write event listener for the given stream.
     *
     * @param resource $stream
     * @return void
     * @see LoopInterface::removeWriteStream()
     */
    public static function removeWriteStream($stream)
    {
        if (self::$instance !== null) {
            self::$instance->removeWriteStream($stream);
        }
    }

    /**
     * Enqueue a callback to be invoked once after the given interval.
     *
     * @param float $interval
     * @param callable $callback
     * @return TimerInterface
     * @see LoopInterface::addTimer()
     */
    public static function addTimer($interval, $callback)
    {
        return (self::$instance ?? self::get())->addTimer($interval, $callback);
    }

    /**
     * Enqueue a callback to be invoked repeatedly after the given interval.
     *
     * @param float $interval
     * @param callable $callback
     * @return TimerInterface
     * @see LoopInterface::addPeriodicTimer()
     */
    public static function addPeriodicTimer($interval, $callback)
    {
        return (self::$instance ?? self::get())->addPeriodicTimer($interval, $callback);
    }

    /**
     * Cancel a pending timer.
     *
     * @param TimerInterface $timer
     * @return void
     * @see LoopInterface::cancelTimer()
     */
    public static function cancelTimer(TimerInterface $timer)
    {
        if (self::$instance !== null) {
            self::$instance->cancelTimer($timer);
        }
    }

    /**
     * Schedule a callback to be invoked on a future tick of the event loop.
     *
     * @param callable $listener
     * @return void
     * @see LoopInterface::futureTick()
     */
    public static function futureTick($listener)
    {
        (self::$instance ?? self::get())->futureTick($listener);
    }

    /**
     * Register a listener to be notified when a signal has been caught by this process.
     *
     * @param int $signal
     * @param callable $listener
     * @return void
     * @see LoopInterface::addSignal()
     */
    public static function addSignal($signal, $listener)
    {
        (self::$instance ?? self::get())->addSignal($signal, $listener);
    }

    /**
     * Removes a previously added signal listener.
     *
     * @param int $signal
     * @param callable $listener
     * @return void
     * @see LoopInterface::removeSignal()
     */
    public static function removeSignal($signal, $listener)
    {
        if (self::$instance !== null) {
            self::$instance->removeSignal($signal, $listener);
        }
    }

    /**
     * Run the event loop until there are no more tasks to perform.
     *
     * @return void
     * @see LoopInterface::run()
     */
    public static function run()
    {
        (self::$instance ?? self::get())->run();
    }

    /**
     * Instruct a running event loop to stop.
     *
     * @return void
     * @see LoopInterface::stop()
     */
    public static function stop()
    {
        self::$stopped = true;
        if (self::$instance !== null) {
            self::$instance->stop();
        }
    }

    /**
     * @return LoopInterface
     */
    private static function create()
    {
        // @codeCoverageIgnoreStart
        if (\function_exists('uv_loop_new')) {
            return new ExtUvLoop();
        }

        if (\class_exists('EvLoop', false)) {
            return new ExtEvLoop();
        }

        if (\class_exists('EventBase', false)) {
            return new ExtEventLoop();
        }

        return new StreamSelectLoop();
        // @codeCoverageIgnoreEnd
    }
}
