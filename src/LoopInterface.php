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
     * The timer callback function MUST be able to accept a single parameter,
     * the timer instance as also returned by this method or you MAY use a
     * function which has no parameters at all.
     *
     * The timer callback function MUST NOT throw an `Exception`.
     * The return value of the timer callback function will be ignored and has
     * no effect, so for performance reasons you're recommended to not return
     * any excessive data structures.
     *
     * Unlike [`addPeriodicTimer()`](#addperiodictimer), this method will ensure
     * the callback will be invoked only once after the given interval.
     * You can invoke [`cancelTimer`](#canceltimer) to cancel a pending timer.
     *
     * ```php
     * $loop->addTimer(0.8, function () {
     *     echo 'world!' . PHP_EOL;
     * });
     *
     * $loop->addTimer(0.3, function () {
     *     echo 'hello ';
     * });
     * ```
     *
     * See also [example #1](examples).
     *
     * If you want to access any variables within your callback function, you
     * can bind arbitrary data to a callback closure like this:
     *
     * ```php
     * function hello(LoopInterface $loop, $name)
     * {
     *     $loop->addTimer(1.0, function () use ($name) {
     *         echo "hello $name\n";
     *     });
     * }
     *
     * hello('Tester');
     * ```
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
     * The timer callback function MUST be able to accept a single parameter,
     * the timer instance as also returned by this method or you MAY use a
     * function which has no parameters at all.
     *
     * The timer callback function MUST NOT throw an `Exception`.
     * The return value of the timer callback function will be ignored and has
     * no effect, so for performance reasons you're recommended to not return
     * any excessive data structures.
     *
     * Unlike [`addTimer()`](#addtimer), this method will ensure the the
     * callback will be invoked infinitely after the given interval or until you
     * invoke [`cancelTimer`](#canceltimer).
     *
     * ```php
     * $timer = $loop->addPeriodicTimer(0.1, function () {
     *     echo 'tick!' . PHP_EOL;
     * });
     *
     * $loop->addTimer(1.0, function () use ($loop, $timer) {
     *     $loop->cancelTimer($timer);
     *     echo 'Done' . PHP_EOL;
     * });
     * ```
     *
     * See also [example #2](examples).
     *
     * If you want to limit the number of executions, you can bind
     * arbitrary data to a callback closure like this:
     *
     * ```php
     * function hello(LoopInterface $loop, $name)
     * {
     *     $n = 3;
     *     $loop->addPeriodicTimer(1.0, function ($timer) use ($name, $loop, &$n) {
     *         if ($n > 0) {
     *             --$n;
     *             echo "hello $name\n";
     *         } else {
     *             $loop->cancelTimer($timer);
     *         }
     *     });
     * }
     *
     * hello('Tester');
     * ```
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
     * See also [`addPeriodicTimer()`](#addperiodictimer) and [example #2](examples).
     *
     * You can use the [`isTimerActive()`](#istimeractive) method to check if
     * this timer is still "active". After a timer is successfully canceled,
     * it is no longer considered "active".
     *
     * Calling this method on a timer instance that has not been added to this
     * loop instance or on a timer that is not "active" (or has already been
     * canceled) has no effect.
     *
     * @param TimerInterface $timer The timer to cancel.
     *
     * @return void
     */
    public function cancelTimer(TimerInterface $timer);

    /**
     * Check if a given timer is active.
     *
     * A timer is considered "active" if it has been added to this loop instance
     * via [`addTimer()`](#addtimer) or [`addPeriodicTimer()`](#addperiodictimer)
     * and has not been canceled via [`cancelTimer()`](#canceltimer) and is not
     * a non-periodic timer that has already been triggered after its interval.
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
