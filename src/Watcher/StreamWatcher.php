<?php

namespace React\EventLoop\Watcher;

final class StreamWatcher
{
    public function __construct(
        public int $key,
        /** @var ?callable */
        public  $readListener = null,
        /** @var ?callable */
        public $writeListener = null,
    ) {
    }
}
