<?php

namespace React\EventLoop;

use Io\Poll\Context;
use Io\Poll\Event;
use Io\Poll\FailedPollWaitException;
use Io\Poll\Watcher;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\Timers;
use React\EventLoop\Watcher\StreamWatcher;
use Time\Duration;

final class IoPollLoop implements LoopInterface
{
    private bool $running = false;
    private Context $context;
    private FutureTickQueue $futureTickQueue;
    private Timers $timers;
    private bool $pcntl;
    private SignalsHandler $signals;
    /** @var array<Watcher> */
    private array $watchers = [];

    public function __construct()
    {
        $this->context = new Context();
        $this->futureTickQueue = new FutureTickQueue();
        $this->timers = new Timers();
        $this->pcntl = \function_exists('pcntl_signal') && \function_exists('pcntl_signal_dispatch') && \function_exists('pcntl_async_signals');
        $this->signals = new SignalsHandler();

        if ($this->pcntl) {
            \pcntl_async_signals(true);
        }
    }

    public function addReadStream($stream, $listener)
    {
        $this->manageStream($stream, Event::Read, $listener);
    }

    public function addWriteStream($stream, $listener)
    {
        $this->manageStream($stream, Event::Write, $listener);
    }

    public function removeReadStream($stream)
    {
        $this->manageStream($stream, Event::Read);
    }

    public function removeWriteStream($stream)
    {
        $this->manageStream($stream, Event::Write);
    }

    public function addTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, false);

        $this->timers->add($timer);

        return $timer;
    }

    public function addPeriodicTimer($interval, $callback)
    {
        $timer = new Timer($interval, $callback, true);

        $this->timers->add($timer);

        return $timer;
    }

    public function cancelTimer(TimerInterface $timer)
    {
        $this->timers->cancel($timer);
    }

    public function futureTick($listener)
    {
        $this->futureTickQueue->add($listener);
    }

    public function addSignal($signal, $listener)
    {
        if ($this->pcntl === false) {
            throw new \BadMethodCallException('Event loop feature "signals" isn\'t supported by the "IoPollLoop"');
        }

        $first = $this->signals->count($signal) === 0;
        $this->signals->add($signal, $listener);

        if ($first) {
            \pcntl_signal($signal, [$this->signals, 'call']);
        }
    }

    public function removeSignal($signal, $listener)
    {
        if (!$this->signals->count($signal)) {
            return;
        }

        $this->signals->remove($signal, $listener);

        if ($this->signals->count($signal) === 0) {
            \pcntl_signal($signal, \SIG_DFL);
        }
    }

    public function run()
    {
        $this->running = true;

        while ($this->running) {
            $this->futureTickQueue->tick();

            $this->timers->tick();

            // Future-tick queue has pending callbacks ...
            if (!$this->running || !$this->futureTickQueue->isEmpty()) {
                $duration = Duration::fromSeconds(0);

                // There is a pending timer, only block until it is due ...
            } elseif ($scheduledAt = $this->timers->getFirst()) {
                $timeout = $scheduledAt - $this->timers->getTime();
                if ($timeout < 0) {
                    $timeout = 0;
                }

                $seconds = (int)$timeout;
                $nanoseconds = (int)(($timeout - $seconds) * 1000000000);

                $duration = Duration::fromSeconds($seconds, $nanoseconds);

                // The only possible event is stream or signal activity, so wait forever ...
            } elseif ($this->watchers || !$this->signals->isEmpty()) {
                $duration = null;

                // There's nothing left to do ...
            } else {
                break;
            }

            try {
                foreach ($this->context->wait($duration) as $watcher) {
                    $stream = $watcher->getHandle()->getStream();
                    $streamWatcher = $watcher->getData();
                    $triggeredEvents = $watcher->getTriggeredEvents();

                    if ($streamWatcher->readListener !== null && in_array(Event::Read, $triggeredEvents)) {
                        \call_user_func($streamWatcher->readListener, $stream);
                    }

                    if ($streamWatcher->writeListener !== null && in_array(Event::Write, $triggeredEvents)) {
                        \call_user_func($streamWatcher->writeListener, $stream);
                    }
                }
            } catch (FailedPollWaitException $failedPollWaitException) {
                if ($failedPollWaitException->getCode() === FailedPollWaitException::ERROR_INTERRUPTED) {
                    \pcntl_signal_dispatch();
                } else {
                    throw $failedPollWaitException;
                }
            }
        }
    }

    public function stop()
    {
        $this->running = false;
    }

    private function manageStream($stream, Event $event, ?callable $listener = null)
    {
        $key = (int) $stream;
        if (!array_key_exists($key, $this->watchers)) {
            if ($listener === null) {
                return;
            }

            $streamWatcher = new StreamWatcher($key);
            $this->updateStreamWatcherListener($streamWatcher, $event, $listener);
            $handle = new \StreamPollHandle($stream);
            $watcher = $this->context->add($handle, [$event], $streamWatcher);
            $this->watchers[$key] = $watcher;

            return;
        }

        if (!isset($watcher)) {
            $watcher = $this->watchers[$key];
        }

        if (!$watcher->isActive()) {
            $watcher->remove();
            unset($this->watchers[$key]);

            return;
        }

        $data = $watcher->getData();
        if ($listener !== null && ($event === Event::Read ? $data->readListener !== null : $data->writeListener !== null)) {
            return;
        }

        $events = $watcher->getWatchedEvents();
        $events = array_filter($events, static function (Event $watchedEvent) use ($event): bool {
            return $watchedEvent === $event;
        });
        if ($listener !== null) {
            $events[] = $event;
        }
        $this->updateStreamWatcherListener($data, $event, $listener);

        if (count($events) > 0 && ($watcher->getData()->readListener !== null || $watcher->getData()->writeListener !== null)) {
            $watcher->modifyEvents($events);

            return;
        }

        $watcher->remove();
        unset($this->watchers[$key]);
    }

    private function updateStreamWatcherListener(StreamWatcher $streamWatcher, Event $event, ?callable $listener = null): void
    {
        if ($event === Event::Read) {
            $streamWatcher->readListener = $listener;
        } elseif ($event === Event::Write) {
            $streamWatcher->writeListener = $listener;
        }
    }
}
