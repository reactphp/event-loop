<?php

namespace React\Tests\EventLoop;

class BinTest extends TestCase
{
    /**
     * @before
     */
    public function setUpBin()
    {
        if (!defined('PHP_BINARY') || defined('HHVM_VERSION')) {
            $this->markTestSkipped('Tests not supported on legacy PHP 5.3 or HHVM');
        }

        chdir(__DIR__ . '/bin/');
    }

    public function testExecuteExampleWithoutLoopRunRunsLoopAndExecutesTicks()
    {
        $output = exec(escapeshellarg(PHP_BINARY) . ' 01-ticks-loop-class.php');

        $this->assertEquals('abc', $output);
    }

    public function testExecuteExampleWithExplicitLoopRunRunsLoopAndExecutesTicks()
    {
        $output = exec(escapeshellarg(PHP_BINARY) . ' 02-ticks-loop-instance.php');

        $this->assertEquals('abc', $output);
    }

    public function testExecuteExampleWithExplicitLoopRunAndStopRunsLoopAndExecutesTicksUntilStopped()
    {
        $output = exec(escapeshellarg(PHP_BINARY) . ' 03-ticks-loop-stop.php');

        $this->assertEquals('abc', $output);
    }
}
