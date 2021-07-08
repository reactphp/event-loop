<?php

namespace React\EventLoop;

interface ForkableLoopInterface extends LoopInterface
{
    /**
     * Create a new event loop after fork
     *
     * This will dispose the current loop instance and return a new
     * stopped event loop instance that can be used within the child
     * process.
     *
     * The child process should call this method or reuseForChildProcess()
     * right after the fork
     *
     * @return ForkableLoopInterface
     */
    public function recreateForChildProcess();

    /**
     * Reinitializes the event loop for re-use in the child process
     *
     * This will keep the event loop in tact so that the forked child
     * can continue using it.
     *
     * The child process should call this method or reuseForChildProcess()
     * right after the fork
     *
     * @return void
     */
    public function reuseForChildProcess();
}
