<?php

use React\EventLoop\Loop;

// autoload for local project development or project installed as dependency for reactphp/reactphp
(@include __DIR__ . '/../../vendor/autoload.php') || require __DIR__ . '/../../../../autoload.php';

Loop::addTimer(10.0, function () {
    echo 'never';
});

/**
 * Ignoring the next line until we raise the minimum PHP version to 7.1
 *
 * @phpstan-ignore-next-line
 */
set_exception_handler(function (Exception $e) {
    echo 'Uncaught error occured' . PHP_EOL;
    Loop::stop();
});

throw new RuntimeException();
