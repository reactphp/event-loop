<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$loop->addSignal(SIGINT, $func = function ($signal) use ($loop, &$func) {
    echo 'Signal: ', (string)$signal, PHP_EOL;
    $loop->removeSignal(SIGINT, $func);
});

$loop->run();
