<?php

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

$timer = Loop::get()->addPeriodicTimer(0.1, function () {
    echo 'tick!' . PHP_EOL;
});

Loop::get()->addTimer(1.0, function () use ($timer) {
    Loop::get()->cancelTimer($timer);
    echo 'Done' . PHP_EOL;
});

Loop::get()->run();
