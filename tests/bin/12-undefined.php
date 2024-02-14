<?php

use React\EventLoop\Loop;

// autoload for local project development or project installed as dependency for reactphp/reactphp
(@include __DIR__ . '/../../vendor/autoload.php') || require __DIR__ . '/../../../../autoload.php';

Loop::get()->addTimer(10.0, function () {
    echo 'never';
});

/**
 * We're ignore this line because the test using this file relies on the error caused by it.
 *
 * @phpstan-ignore-next-line
 */
$undefined->foo('bar');
