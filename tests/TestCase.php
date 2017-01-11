<?php

namespace React\Tests\EventLoop;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function expectCallableExactly($amount)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->exactly($amount))
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        $stub = 'React\Tests\EventLoop\CallableStub';
        
        if (method_exists($this, 'createMock')) {
            return $this->createMock($stub);
        }
        
        if (method_exists($this, 'getMockBuilder')) {
            return $this->getMockBuilder($stub)->getMock();
        }
        
        return $this->getMock($stub);
    }
}
