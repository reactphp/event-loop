<?php

namespace React\Tests\EventLoop;

use React\EventLoop\LibUvLoop;

class LibUvEventLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        if (!function_exists('uv_default_loop')) {
            $this->markTestSkipped('libuv tests skipped because ext-uv is not installed.');
        }

        if(!is_null($this->loop)) {
            return $this->loop;
        }

        return new LibUvLoop();
    }

    public function testLibEventConstructor()
    {
        $loop = new LibUvLoop();
    }

    public function createStream()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        stream_set_blocking($sockets[0], 0);
        stream_set_blocking($sockets[1], 0);

        return $sockets;
    }

    public function writeToStream($stream, $content)
    {
        fwrite($stream, $content);
    }

    /**
     * Make sure event loop throws exception, as libuv only supports
     * network socket streams.
     * @group socketonly
     */
    public function testCanOnlyAddSocketStream()
    {
        $this->setExpectedException('InvalidArgumentException');

        $fp = fopen("php://temp", "r+");
        $this->loop->addReadStream($fp, function(){});

    }

    public function testAddReadStream()
    {
        $streams = $this->createStream();

        $this->loop->addReadStream($streams[1], $this->expectCallableExactly(2));

        $this->writeToStream($streams[0], "foo\n");
        $this->loop->tick();

        $this->writeToStream($streams[0], "bar\n");
        $this->loop->tick();
    }

    public function testAddWriteStream()
    {
        $input = $this->createStream();

        $this->loop->addWriteStream($input[0], $this->expectCallableExactly(2));
        $this->loop->tick();
        $this->loop->tick();
    }

    public function testRemoveReadStreamInstantly()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input[1], $this->expectCallableNever());
        $this->loop->removeReadStream($input[1]);

        $this->writeToStream($input[0], "bar\n");
        $this->loop->tick();

        //cleanup
        $this->loop->removeStream($input[0]);
    }

    public function testRemoveReadStreamAfterReading()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input[1], $this->expectCallableOnce());

        $this->writeToStream($input[0], "foo\n");
        $this->loop->tick();

        $this->loop->removeReadStream($input[1]);

        $this->writeToStream($input[0], "bar\n");
        $this->loop->tick();

        //cleanup
        $this->loop->removeStream($input[0]);
    }

    public function testRemoveWriteStreamInstantly()
    {
        $input = $this->createStream();

        $this->loop->addWriteStream($input[0], $this->expectCallableNever());
        $this->loop->removeWriteStream($input[0]);
        $this->loop->tick();
    }

    public function testRemoveWriteStreamAfterWriting()
    {
        $input = $this->createStream();

        $this->loop->addWriteStream($input[0], $this->expectCallableOnce());
        $this->loop->tick();

        $this->loop->removeWriteStream($input[0]);
        $this->loop->tick();
    }

    public function testRemoveStreamInstantly()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input[0], $this->expectCallableNever());
        $this->loop->addWriteStream($input[0], $this->expectCallableNever());
        $this->loop->removeStream($input[0]);

        $this->writeToStream($input[0], "bar\n");
        $this->loop->tick();
    }

    public function testRemoveStreamForReadOnly()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input[1], $this->expectCallableNever());
        $this->loop->addWriteStream($input[0], $this->expectCallableOnce());
        $this->loop->removeReadStream($input[1]);

        $this->writeToStream($input[0], "foo\n");
        $this->loop->tick();

        //cleanup
        $this->loop->removeStream($input[0]);
    }

    public function testRemoveStreamForWriteOnly()
    {
        $input = $this->createStream();

        $this->writeToStream($input[0], "foo\n");

        $this->loop->addReadStream($input[1], $this->expectCallableOnce());
        $this->loop->addWriteStream($input[0], $this->expectCallableNever());
        $this->loop->removeWriteStream($input[0]);

        $this->loop->tick();

        //cleanup
        $this->loop->removeStream($input[1]);
    }

    public function testRemoveStream()
    {
        $input = $this->createStream();

        $this->loop->addReadStream($input[1], $this->expectCallableOnce());
        $this->loop->addWriteStream($input[0], $this->expectCallableOnce());

        $this->writeToStream($input[0], "bar\n");
        $this->loop->tick();

        $this->loop->removeStream($input[0]);
        $this->loop->removeStream($input[1]);

        $this->writeToStream($input[0], "bar\n");
        $this->loop->tick();
    }

    public function testRemoveInvalid()
    {
        $input = $this->createStream();

        // remove a valid stream from the event loop that was never added in the first place
        $this->loop->removeReadStream($input[0]);
        $this->loop->removeWriteStream($input[0]);
        $this->loop->removeStream($input[0]);
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
        $this->loop->addReadStream($input[1], function ($stream) use ($input) {
            $this->loop->removeStream($stream);
            $this->loop->removeStream($input[0]);
        });

        $this->writeToStream($input[0], "foo\n");

        $this->assertRunFasterThan(0.005);
    }

    /** @test */
    public function stopShouldStopRunningLoop()
    {
        $input = $this->createStream();

        $loop = $this->loop;
        $this->loop->addReadStream($input[1], function ($stream) use ($loop) {
            $loop->stop();
        });

        $this->writeToStream($input[0], "foo\n");

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
        $input = $this->createStream();
        // $stream2 = $this->createStream();

        $loop = $this->loop;
        $loop->addReadStream($input[1], function ($stream) use ($loop, $input) {
            // stream1 is readable, remove stream2 as well => this will invalidate its callback
            $loop->removeReadStream($stream);
            $loop->removeReadStream($input[0]);
        });

        // this callback would have to be called as well, but the first stream already removed us
        $loop->addReadStream($input[0], $this->expectCallableNever());

        $this->writeToStream($input[0], "foo\n");
        $this->writeToStream($input[1], "foo\n");

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
            $stream[0],
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
            $stream[0],
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
            $stream[0],
            function () use ($stream) {
                $this->loop->removeStream($stream[0]);
                $this->loop->nextTick(
                    function () {
                        echo 'next-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('next-tick' . PHP_EOL);

        $this->loop->run();

        $this->writeToStream($stream[0], "foo\n");
    }

    public function testNextTickEventGeneratedByFutureTick()
    {
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
            $stream[0],
            function () {
                echo 'stream' . PHP_EOL;
            }
        );

        $this->loop->futureTick(
            function () {
                echo 'future-tick' . PHP_EOL;
            }
        );

        $this->writeToStream($stream[0], "foo\n");
        $this->expectOutputString('future-tick' . PHP_EOL . 'stream' . PHP_EOL);

        $this->loop->tick();
    }

    public function testRecursiveFutureTick()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream[0],
            function () use ($stream) {
                echo 'stream' . PHP_EOL;
                $this->loop->removeWriteStream($stream[0]);
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

        $this->writeToStream($stream[0], "foo\n");
    }

    public function testRunWaitsForFutureTickEvents()
    {
        $stream = $this->createStream();

        $this->loop->addWriteStream(
            $stream[0],
            function () use ($stream) {
                $this->loop->removeStream($stream[0]);
                $this->loop->futureTick(
                    function () {
                        echo 'future-tick' . PHP_EOL;
                    }
                );
            }
        );

        $this->expectOutputString('future-tick' . PHP_EOL);

        $this->loop->run();

        $this->writeToStream($stream[0], "foo\n");
    }

    public function testFutureTickEventGeneratedByNextTick()
    {
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
}
