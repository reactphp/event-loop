<?php

namespace React\Tests\EventLoop;

use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;

class StreamSelectLoopTest extends AbstractLoopTest
{
    /**
     * @after
     */
    protected function tearDownSignalHandlers()
    {
        parent::tearDown();
        if (strncmp($this->getName(false), 'testSignal', 10) === 0 && extension_loaded('pcntl')) {
            $this->resetSignalHandlers();
        }
    }

    public function createLoop()
    {
        return new StreamSelectLoop();
    }

    public function testStreamSelectTimeoutEmulation()
    {
        $this->loop->addTimer(
            0.05,
            $this->expectCallableOnce()
        );

        $start = microtime(true);

        $this->loop->run();

        $end = microtime(true);
        $interval = $end - $start;

        $this->assertGreaterThan(0.04, $interval);
    }

    public function testStreamSelectReportsWarningForStreamWithFilter()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on legacy HHVM');
        }

        $stream = tmpfile();
        stream_filter_append($stream, 'string.rot13');

        $this->loop->addReadStream($stream, $this->expectCallableNever());

        $loop = $this->loop;
        $this->loop->futureTick(function () use ($loop, $stream) {
            $loop->futureTick(function () use ($loop, $stream) {
                $loop->removeReadStream($stream);
            });
        });

        $error = null;
        $previous = set_error_handler(function ($_, $errstr) use (&$error) {
            $error = $errstr;
        });

        try {
            $this->loop->run();
        } catch (\ValueError $e) {
            // ignore ValueError for PHP 8+ due to empty stream array
        }

        restore_error_handler();

        $this->assertNotNull($error);

        $now = set_error_handler(function () { });
        restore_error_handler();
        $this->assertEquals($previous, $now);
    }

    public function testStreamSelectThrowsWhenCustomErrorHandlerThrowsForStreamWithFilter()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Not supported on legacy HHVM');
        }

        $stream = tmpfile();
        stream_filter_append($stream, 'string.rot13');

        $this->loop->addReadStream($stream, $this->expectCallableNever());

        $loop = $this->loop;
        $this->loop->futureTick(function () use ($loop, $stream) {
            $loop->futureTick(function () use ($loop, $stream) {
                $loop->removeReadStream($stream);
            });
        });

        $previous = set_error_handler(function ($_, $errstr) {
            throw new \RuntimeException($errstr);
        });

        $e = null;
        try {
            $this->loop->run();
            restore_error_handler();
            $this->fail();
        } catch (\RuntimeException $e) {
            restore_error_handler();
        } catch (\ValueError $e) {
            restore_error_handler(); // PHP 8+
            $e = $e->getPrevious();
        }

        $this->assertInstanceOf('RuntimeException', $e);

        $now = set_error_handler(function () { });
        restore_error_handler();
        $this->assertEquals($previous, $now);
    }

    public function signalProvider()
    {
        return array(
            array('SIGUSR1'),
            array('SIGHUP'),
            array('SIGTERM'),
        );
    }

    /**
     * Test signal interrupt when no stream is attached to the loop
     * @dataProvider signalProvider
     * @requires extension pcntl
     * @requires function pcntl_signal()
     * @requires function pcntl_signal_dispatch()
     */
    public function testSignalInterruptNoStream($signal)
    {
        // dispatch signal handler every 10ms for 0.1s
        $check = $this->loop->addPeriodicTimer(0.01, function() {
            pcntl_signal_dispatch();
        });
        $loop = $this->loop;
        $loop->addTimer(0.1, function () use ($check, $loop) {
            $loop->cancelTimer($check);
        });

        $handled = false;
        $this->assertTrue(pcntl_signal(constant($signal), function () use (&$handled) {
            $handled = true;
        }));

        // spawn external process to send signal to current process id
        $this->forkSendSignal($signal);

        $this->loop->run();
        $this->assertTrue($handled);
    }

    /**
     * Test signal interrupt when a stream is attached to the loop
     * @dataProvider signalProvider
     * @requires extension pcntl
     * @requires function pcntl_signal()
     * @requires function pcntl_signal_dispatch()
     */
    public function testSignalInterruptWithStream($signal)
    {
        // dispatch signal handler every 10ms
        $this->loop->addPeriodicTimer(0.01, function() {
            pcntl_signal_dispatch();
        });

        // add stream to the loop
        $loop = $this->loop;
        list($writeStream, $readStream) = $this->createSocketPair();
        $loop->addReadStream($readStream, function ($stream) use ($loop) {
            /** @var $loop LoopInterface */
            $read = fgets($stream);
            if ($read === "end loop\n") {
                $loop->stop();
            }
        });
        $this->loop->addTimer(0.1, function() use ($writeStream) {
            fwrite($writeStream, "end loop\n");
        });

        $handled = false;
        $this->assertTrue(pcntl_signal(constant($signal), function () use (&$handled) {
            $handled = true;
        }));

        // spawn external process to send signal to current process id
        $this->forkSendSignal($signal);

        $this->loop->run();

        $this->assertTrue($handled);
    }

    /**
     * reset all signal handlers to default
     */
    protected function resetSignalHandlers()
    {
        foreach($this->signalProvider() as $signal) {
            pcntl_signal(constant($signal[0]), SIG_DFL);
        }
    }

    /**
     * fork child process to send signal to current process id
     */
    protected function forkSendSignal($signal)
    {
        $currentPid = posix_getpid();
        $childPid = pcntl_fork();
        if ($childPid == -1) {
            $this->fail("Failed to fork child process!");
        } else if ($childPid === 0) {
            // this is executed in the child process
            usleep(20000);
            posix_kill($currentPid, constant($signal));
            die();
        }
    }
}
