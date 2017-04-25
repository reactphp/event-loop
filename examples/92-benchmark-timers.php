<?php

use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

$n = isset($argv[1]) ? (int)$argv[1] : 1000 * 100;

for ($i = 0; $i < $n; ++$i) {
    $loop->addTimer(0, function () { });
}

$loop->run();
