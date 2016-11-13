<?php

/**
 * Usage, runs indefinetly with the loop from Factory::create: php test-memory.php
 * Usage, runs for 6 seconds (anything lower than 5 seconds will be ignored): php test-memory.php 6
 * Usage, runs with the stream select loop for 6 seconds: php test-memory.php 6 StreamSelect
 */

use React\EventLoop\Factory;
use React\EventLoop\Timer\TimerInterface;

require __DIR__.'/vendor/autoload.php';

if (isset($argv[2]) && class_exists('React\EventLoop\\' . $argv[2] . 'Loop')) {
    $class = 'React\EventLoop\\' . $argv[2] . 'Loop';
    $loop = new $class();
} else {
    $loop = Factory::create();
}

$i = 0;

if (isset($argv[1]) && 5 < (int)$argv[1]) {
    $loop->addTimer((int)$argv[1], function (TimerInterface $timer) {
        $timer->getLoop()->stop();
    });

}

$loop->addPeriodicTimer(0.001, function () use (&$i, $loop) {
    $i++;

    $loop->addPeriodicTimer(1, function (TimerInterface $timer) {
        $timer->cancel();
    });
});

$loop->addPeriodicTimer(2, function () use (&$i) {
    $kmem = round(memory_get_usage() / 1024);
    $kmemReal = round(memory_get_usage(true) / 1024);
    echo "Runs:\t\t\t$i\n";
    echo "Memory (internal):\t$kmem KiB\n";
    echo "Memory (real):\t\t$kmemReal KiB\n";
    echo str_repeat('-', 50), "\n";
});

echo "Loop\t\t\t", get_class($loop), "\n";
echo "Time\t\t\t", date('r'), "\n";

echo str_repeat('-', 50), "\n";

$beginTime = time();
$loop->run();
$endTime = time();
$timeTaken = $endTime - $beginTime;

echo "Loop\t\t\t", get_class($loop), "\n";
echo "Time\t\t\t", date('r'), "\n";
echo "Time taken\t\t", $timeTaken, " seconds\n";
echo "Runs per second\t\t", round($i / $timeTaken), "\n";
