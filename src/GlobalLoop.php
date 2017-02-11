<?php

namespace React\EventLoop;

final class GlobalLoop
{
    /**
     * @internal
     *
     * @var LoopInterface
     */
    public static $loop;

    private static $factory = ['React\EventLoop\Factory', 'create'];

    public static function setFactory(callable $factory)
    {
        if (self::$loop) {
            throw new \LogicException(
                'Setting a factory after the global loop has been created is not allowed.'
            );
        }

        self::$factory = $factory;
    }

    /**
     * @return LoopInterface
     */
    public static function get()
    {
        if (self::$loop) {
            return self::$loop;
        }

        return self::$loop = self::create();
    }

    /**
     * @return LoopInterface
     */
    public static function create()
    {
        $loop = call_user_func(self::$factory);

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
