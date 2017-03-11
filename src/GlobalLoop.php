<?php

namespace React\EventLoop;

final class GlobalLoop
{
    /**
     * @var LoopInterface
     */
    private static $loop;

    private static $factory;

    private static $didRun = false;
    private static $disableRunOnShutdown = false;

    public static function setFactory(callable $factory = null)
    {
        if (self::$didRun) {
            throw new \LogicException(
                'Setting a factory after the global loop has been started is not allowed.'
            );
        }

        self::$factory = $factory;
    }

    public function disableRunOnShutdown()
    {
        self::$disableRunOnShutdown = true;
    }

    /**
     * @return LoopInterface
     */
    public static function get()
    {
        if (self::$loop) {
            return self::$loop;
        }

        register_shutdown_function(function () {
            if (self::$disableRunOnShutdown || self::$didRun || !self::$loop) {
                return;
            }

            self::$loop->run();
        });

        self::$loop = self::create();

        self::$loop->futureTick(function () {
            self::$didRun = true;
        });

        return self::$loop;
    }

    public static function reset()
    {
        self::$loop = null;
        self::$didRun = false;
    }

    /**
     * @return LoopInterface
     */
    public static function create()
    {
        if (self::$factory) {
            return self::createFromCustomFactory(self::$factory);
        }

        return Factory::create();
    }

    private static function createFromCustomFactory(callable $factory)
    {
        $loop = call_user_func($factory);

        if (!$loop instanceof LoopInterface) {
            throw new \LogicException(
                sprintf(
                    'The GlobalLoop factory must return an instance of LoopInterface but returned %s.',
                    is_object($loop) ? get_class($loop) : gettype($loop)
                )
            );
        }

        return $loop;
    }
}
