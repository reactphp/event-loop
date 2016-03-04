<?php

namespace React\Tests\EventLoop;

use React\EventLoop\LoopInterface;

abstract class AbstractLoopTest extends TestCase
{
    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    public function setUp()
    {
        parent::setUp();
        $this->loop = $this->createLoop();
    }

    protected function tearDown()
    {
        parent::tearDown();
        if (strncmp($this->getName(false), 'testSignal', 10) === 0 && extension_loaded('pcntl')) {
            $this->resetSignalHandlers();
        }
    }

    abstract public function createLoop();

    public function createStream()
    {
        return fopen('php://temp', 'r+');
    }

    public function writeToStream($stream, $content)
    {
        fwrite($stream, $content);
        rewind($stream);
    }

    public function testAddReadStream()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input, $this->expectCallableExactly(2));

        $this->writeToStream($input, "foo\n");
        $this->loop->tick();

        $this->writeToStream($input, "bar\n");
        $this->loop->tick();
    }

    public function testAddWriteStream()
    {
        $input = $this->createStream();

        $this->loop->addWriteStream($input, $this->expectCallableExactly(2));
        $this->loop->tick();
        $this->loop->tick();
    }

    public function testRemoveReadStreamInstantly()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input, $this->expectCallableNever());
        $this->loop->removeReadStream($input);

        $this->writeToStream($input, "bar\n");
        $this->loop->tick();
    }

    public function testRemoveReadStreamAfterReading()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input, $this->expectCallableOnce());

        $this->writeToStream($input, "foo\n");
        $this->loop->tick();

        $this->loop->removeReadStream($input);

        $this->writeToStream($input, "bar\n");
        $this->loop->tick();
    }

    public function testRemoveWriteStreamInstantly()
    {
        $input = $this->createStream();

        $this->loop->addWriteStream($input, $this->expectCallableNever());
        $this->loop->removeWriteStream($input);
        $this->loop->tick();
    }

    public function testRemoveWriteStreamAfterWriting()
    {
        $input = $this->createStream();

        $this->loop->addWriteStream($input, $this->expectCallableOnce());
        $this->loop->tick();

        $this->loop->removeWriteStream($input);
        $this->loop->tick();
    }

    public function testRemoveStreamInstantly()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input, $this->expectCallableNever());
        $this->loop->addWriteStream($input, $this->expectCallableNever());
        $this->loop->removeStream($input);

        $this->writeToStream($input, "bar\n");
        $this->loop->tick();
    }

    public function testRemoveStreamForReadOnly()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input, $this->expectCallableNever());
        $this->loop->addWriteStream($input, $this->expectCallableOnce());
        $this->loop->removeReadStream($input);

        $this->writeToStream($input, "foo\n");
        $this->loop->tick();
    }

    public function testRemoveStreamForWriteOnly()
    {
        $input = $this->createStream();

        $this->writeToStream($input, "foo\n");

        $this->loop->addReadStream($input, $this->expectCallableOnce());
        $this->loop->addWriteStream($input, $this->expectCallableNever());
        $this->loop->removeWriteStream($input);

        $this->loop->tick();
    }

    public function testRemoveStream()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input, $this->expectCallableOnce());
        $this->loop->addWriteStream($input, $this->expectCallableOnce());

        $this->writeToStream($input, "bar\n");
        $this->loop->tick();

        $this->loop->removeStream($input);

        $this->writeToStream($input, "bar\n");
        $this->loop->tick();
    }

    public function testRemoveInvalid()
    {
        $stream = $this->createStream();

        // remove a valid stream from the event loop that was never added in the first place
        $this->loop->removeReadStream($stream);
        $this->loop->removeWriteStream($stream);
        $this->loop->removeStream($stream);
    }

    /** @test */
    public function emptyRunShouldSimplyReturn()
    {
        $this->assertRunFasterThan(0.005);
    }

    /** @test */
    public function runShouldReturnWhenNoMoreFds()
    {
        $input = $this->createStream();

        $loop = $this->loop;
        $this->loop->addReadStream($input, function ($stream) use ($loop) {
            $loop->removeStream($stream);
        });

        $this->writeToStream($input, "foo\n");

        $this->assertRunFasterThan(0.005);
    }

    /** @test */
    public function stopShouldStopRunningLoop()
    {
        $input = $this->createStream();

        $loop = $this->loop;
        $this->loop->addReadStream($input, function ($stream) use ($loop) {
            $loop->stop();
        });

        $this->writeToStream($input, "foo\n");

        $this->assertRunFasterThan(0.005);
    }

    public function testStopShouldPreventRunFromBlocking()
    {
        $this->loop->addTimer(
            1,
            function () {
                $this->fail('Timer was executed.');
            }
        );

        $this->loop->nextTick(
            function () {
                $this->loop->stop();
            }
        );

        $this->assertRunFasterThan(0.005);
    }

    public function testIgnoreRemovedCallback()
    {
        // two independent streams, both should be readable right away
        $stream1 = $this->createStream();
        $stream2 = $this->createStream();

        $loop = $this->loop;
        $loop->addReadStream($stream1, function ($stream) use ($loop, $stream2) {
            // stream1 is readable, remove stream2 as well => this will invalidate its callback
            $loop->removeReadStream($stream);
            $loop->removeReadStream($stream2);
        });

        // this callback would have to be called as well, but the first stream already removed us
        $loop->addReadStream($stream2, $this->expectCallableNever());

        $this->writeToStream($stream1, "foo\n");
        $this->writeToStream($stream2, "foo\n");

        $loop->run();
    }

    public function testNextTick()
    {
        $called = false;

        $callback = function ($loop) use (&$called) {
            $this->assertSame($this->loop, $loop);
            $called = true;
        };

        $this->loop->nextTick($callback);

        $this->assertFalse($called);

        $this->loop->tick();

        $this->assertTrue($called);
    }

    public function testNextTickFiresBeforeIO()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream,
            function () {
                echo 'stream' . PHP_EOL;
            }
        );

        $this->loop->nextTick(
            function () {
                echo 'next-tick' . PHP_EOL;
            }
        );

        $this->expectOutputString('next-tick' . PHP_EOL . 'stream' . PHP_EOL);

        $this->loop->tick();
    }

    public function testRecursiveNextTick()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream,
            function () {
                echo 'stream' . PHP_EOL;
            }
        );

        $this->loop->nextTick(
            function () {
                $this->loop->nextTick(
                    function () {
                        echo 'next-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('next-tick' . PHP_EOL . 'stream' . PHP_EOL);

        $this->loop->tick();
    }

    public function testRunWaitsForNextTickEvents()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream,
            function () use ($stream) {
                $this->loop->removeStream($stream);
                $this->loop->nextTick(
                    function () {
                        echo 'next-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('next-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testNextTickEventGeneratedByFutureTick()
    {
        $stream = $this->createStream();

        $this->loop->futureTick(
            function () {
                $this->loop->nextTick(
                    function () {
                        echo 'next-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('next-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testNextTickEventGeneratedByTimer()
    {
        $this->loop->addTimer(
            0.001,
            function () {
                $this->loop->nextTick(
                    function () {
                        echo 'next-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('next-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testFutureTick()
    {
        $called = false;

        $callback = function ($loop) use (&$called) {
            $this->assertSame($this->loop, $loop);
            $called = true;
        };

        $this->loop->futureTick($callback);

        $this->assertFalse($called);

        $this->loop->tick();

        $this->assertTrue($called);
    }

    public function testFutureTickFiresBeforeIO()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream,
            function () {
                echo 'stream' . PHP_EOL;
            }
        );

        $this->loop->futureTick(
            function () {
                echo 'future-tick' . PHP_EOL;
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL . 'stream' . PHP_EOL);

        $this->loop->tick();
    }

    public function testRecursiveFutureTick()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream,
            function () use ($stream) {
                echo 'stream' . PHP_EOL;
                $this->loop->removeWriteStream($stream);
            }
        );

        $this->loop->futureTick(
            function () {
                echo 'future-tick-1' . PHP_EOL;
                $this->loop->futureTick(
                    function () {
                        echo 'future-tick-2' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick-1' . PHP_EOL . 'stream' . PHP_EOL . 'future-tick-2' . PHP_EOL);

        $this->loop->run();
    }

    public function testRunWaitsForFutureTickEvents()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream,
            function () use ($stream) {
                $this->loop->removeStream($stream);
                $this->loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testFutureTickEventGeneratedByNextTick()
    {
        $stream = $this->createStream();

        $this->loop->nextTick(
            function () {
                $this->loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();
    }

    public function testFutureTickEventGeneratedByTimer()
    {
        $this->loop->addTimer(
            0.001,
            function () {
                $this->loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();
    }

    private function assertRunFasterThan($maxInterval)
    {
        $start = microtime(true);

        $this->loop->run();

        $end = microtime(true);
        $interval = $end - $start;

        $this->assertLessThan($maxInterval, $interval);
    }

    public function signalProvider()
    {
        return [
            ['SIGUSR1', SIGUSR1],
            ['SIGHUP', SIGHUP],
            ['SIGTERM', SIGTERM],
        ];
    }

    private $_signalHandled = false;

    /**
     * Test signal interrupt when no stream is attached to the loop
     * @dataProvider signalProvider
     */
    public function testSignalInterruptNoStream($sigName, $signal)
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('"pcntl" extension is required to run this test.');
        }

        // dispatch signal handler once before signal is sent and once after
        $this->loop->addTimer(0.01, function() { pcntl_signal_dispatch(); });
        $this->loop->addTimer(0.03, function() { pcntl_signal_dispatch(); });
        if (defined('HHVM_VERSION')) {
            // hhvm startup is slow so we need to add another handler much later
            $this->loop->addTimer(0.5, function() { pcntl_signal_dispatch(); });
        }

        $this->setUpSignalHandler($signal);

        // spawn external process to send signal to current process id
        $this->forkSendSignal($signal);
        $this->loop->run();
        $this->assertTrue($this->_signalHandled);
    }

    /**
     * Test signal interrupt when a stream is attached to the loop
     * @dataProvider signalProvider
     */
    public function testSignalInterruptWithStream($sigName, $signal)
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('"pcntl" extension is required to run this test.');
        }

        // dispatch signal handler every 10ms
        $this->loop->addPeriodicTimer(0.01, function() { pcntl_signal_dispatch(); });

        // add stream to the loop
        list($writeStream, $readStream) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->loop->addReadStream($readStream, function($stream, $loop) {
            /** @var $loop LoopInterface */
            $read = fgets($stream);
            if ($read === "end loop\n") {
                $loop->stop();
            }
        });
        $this->loop->addTimer(0.05, function() use ($writeStream) {
            fwrite($writeStream, "end loop\n");
        });

        $this->setUpSignalHandler($signal);

        // spawn external process to send signal to current process id
        $this->forkSendSignal($signal);

        $this->loop->run();

        $this->assertTrue($this->_signalHandled);
    }

    /**
     * add signal handler for signal
     */
    protected function setUpSignalHandler($signal)
    {
        $this->_signalHandled = false;
        $this->assertTrue(pcntl_signal($signal, function() { $this->_signalHandled = true; }));
    }

    /**
     * reset all signal handlers to default
     */
    protected function resetSignalHandlers()
    {
        foreach($this->signalProvider() as $signal) {
            pcntl_signal($signal[1], SIG_DFL);
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
            posix_kill($currentPid, $signal);
            die();
        }
    }
}
