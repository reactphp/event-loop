<?php

namespace React\Tests\EventLoop;

use React\EventLoop\ExtEvLoop;

class ExtEvLoopTest extends AbstractLoopTest
{
    private $file;

    public function createLoop()
    {
        if (!class_exists('EvLoop')) {
            $this->markTestSkipped('ev tests skipped because pecl/ev is not installed.');
        }

        return new ExtEvLoop();
    }

    public function tearDown()
    {
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function createStream()
    {
        $this->file = tempnam(sys_get_temp_dir(), 'react-');

        $stream = fopen($this->file, 'r+');

        return $stream;
    }
    
    public function testEvConstructor()
    {
        $loop = new ExtEvLoop();
    }
}
