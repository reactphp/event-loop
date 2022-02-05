<?php

use React\EventLoop\Loop;

// autoload for local project development or project installed as dependency for reactphp/reactphp
(@include __DIR__ . '/../../vendor/autoload.php') || require __DIR__ . '/../../../../autoload.php';

Loop::addTimer(10.0, function () {
    echo 'never';
});

set_exception_handler(function (Exception $e) {
    echo 'Uncaught error occured' . PHP_EOL;
    Loop::stop();
});

throw new RuntimeException();
