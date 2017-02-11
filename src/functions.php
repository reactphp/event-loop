<?php

namespace React\EventLoop;

use React\EventLoop\Timer\TimerInterface;

/**
 * Register a listener to the global event loop to be notified when a stream is
 * ready to read.
 *
 * @param resource $stream   The PHP stream resource to check.
 * @param callable $listener Invoked when the stream is ready.
 */
function addReadStream($stream, callable $listener)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->addReadStream($stream, $listener);
}

/**
 * Register a listener to the global event loop to be notified when a stream is
 * ready to write.
 *
 * @param resource $stream   The PHP stream resource to check.
 * @param callable $listener Invoked when the stream is ready.
 */
function addWriteStream($stream, callable $listener)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->addWriteStream($stream, $listener);
}

/**
 * Remove the read event listener from the global event loop for the given
 * stream.
 *
 * @param resource $stream The PHP stream resource.
 */
function removeReadStream($stream)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->removeReadStream($stream);
}

/**
 * Remove the write event listener from the global event loop for the given
 * stream.
 *
 * @param resource $stream The PHP stream resource.
 */
function removeWriteStream($stream)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->removeWriteStream($stream);
}

/**
 * Remove all listeners from the global event loop for the given stream.
 *
 * @param resource $stream The PHP stream resource.
 */
function removeStream($stream)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->removeStream($stream);
}

/**
 * Enqueue a callback to the global event loop to be invoked once after the
 * given interval.
 *
 * The execution order of timers scheduled to execute at the same time is
 * not guaranteed.
 *
 * @param int|float $interval The number of seconds to wait before execution.
 * @param callable  $callback The callback to invoke.
 *
 * @return TimerInterface
 */
function addTimer($interval, callable $callback)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    return $loop->addTimer($interval, $callback);
}

/**
 * Enqueue a callback to the global event loop to be invoked repeatedly after
 * the given interval.
 *
 * The execution order of timers scheduled to execute at the same time is
 * not guaranteed.
 *
 * @param int|float $interval The number of seconds to wait before execution.
 * @param callable  $callback The callback to invoke.
 *
 * @return TimerInterface
 */
function addPeriodicTimer($interval, callable $callback)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    return $loop->addPeriodicTimer($interval, $callback);
}

/**
 * Schedule a callback to be invoked on a future tick of the global event loop.
 *
 * Callbacks are guaranteed to be executed in the order they are enqueued.
 *
 * @param callable $listener The callback to invoke.
 */
function futureTick(callable $listener)
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->futureTick($listener);
}

/**
 * Run the global event loop until there are no more tasks to perform.
 */
function run()
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->run();
}

/**
 * Instruct the running global event loop to stop.
 */
function stop()
{
    $loop = GlobalLoop::$loop ?: GlobalLoop::get();

    $loop->stop();
}
