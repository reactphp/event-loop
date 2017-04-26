<?php

namespace React\EventLoop;

use React\EventLoop\Timer\TimerInterface;

interface LoopInterface
{
    /**
     * Register a listener to be notified when a stream is ready to read.
     *
     * @param resource $stream   The PHP stream resource to check.
     * @param callable $listener Invoked when the stream is ready.
     */
    public function addReadStream($stream, callable $listener);

    /**
     * Register a listener to be notified when a stream is ready to write.
     *
     * @param resource $stream   The PHP stream resource to check.
     * @param callable $listener Invoked when the stream is ready.
     */
    public function addWriteStream($stream, callable $listener);

    /**
     * Remove the read event listener for the given stream.
     *
     * @param resource $stream The PHP stream resource.
     */
    public function removeReadStream($stream);

    /**
     * Remove the write event listener for the given stream.
     *
     * @param resource $stream The PHP stream resource.
     */
    public function removeWriteStream($stream);

    /**
     * Remove all listeners for the given stream.
     *
     * @param resource $stream The PHP stream resource.
     */
    public function removeStream($stream);

    /**
     * Enqueue a callback to be invoked once after the given interval.
     *
     * The execution order of timers scheduled to execute at the same time is
     * not guaranteed.
     *
     * @param int|float $interval The number of seconds to wait before execution.
     * @param callable  $callback The callback to invoke.
     *
     * @return TimerInterface
     */
    public function addTimer($interval, callable $callback);

    /**
     * Enqueue a callback to be invoked repeatedly after the given interval.
     *
     * The execution order of timers scheduled to execute at the same time is
     * not guaranteed.
     *
     * @param int|float $interval The number of seconds to wait before execution.
     * @param callable  $callback The callback to invoke.
     *
     * @return TimerInterface
     */
    public function addPeriodicTimer($interval, callable $callback);

    /**
     * Cancel a pending timer.
     *
     * @param TimerInterface $timer The timer to cancel.
     */
    public function cancelTimer(TimerInterface $timer);

    /**
     * Check if a given timer is active.
     *
     * @param TimerInterface $timer The timer to check.
     *
     * @return boolean True if the timer is still enqueued for execution.
     */
    public function isTimerActive(TimerInterface $timer);

    /**
     * Schedule a callback to be invoked on a future tick of the event loop.
     *
     * This works very much similar to timers with an interval of zero seconds,
     * but does not require the overhead of scheduling a timer queue.
     *
     * The tick callback function MUST be able to accept zero parameters.
     *
     * The tick callback function MUST NOT throw an `Exception`.
     * The return value of the tick callback function will be ignored and has
     * no effect, so for performance reasons you're recommended to not return
     * any excessive data structures.
     *
     * If you want to access any variables within your callback function, you
     * can bind arbitrary data to a callback closure like this:
     *
     * ```php
     * function hello(LoopInterface $loop, $name)
     * {
     *     $loop->futureTick(function () use ($name) {
     *         echo "hello $name\n";
     *     });
     * }
     *
     * hello('Tester');
     * ```
     *
     * Unlike timers, tick callbacks are guaranteed to be executed in the order
     * they are enqueued.
     * Also, once a callback is enqueued, there's no way to cancel this operation.
     *
     * This is often used to break down bigger tasks into smaller steps (a form
     * of cooperative multitasking).
     *
     * ```php
     * $loop->futureTick(function () {
     *     echo 'b';
     * });
     * $loop->futureTick(function () {
     *     echo 'c';
     * });
     * echo 'a';
     * ```
     *
     * See also [example #3](examples).
     *
     * @param callable $listener The callback to invoke.
     *
     * @return void
     */
    public function futureTick(callable $listener);

    /**
     * Run the event loop until there are no more tasks to perform.
     */
    public function run();

    /**
     * Instruct a running event loop to stop.
     */
    public function stop();
}
