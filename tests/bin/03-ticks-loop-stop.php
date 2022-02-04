<?php

use React\EventLoop\Loop;

// autoload for local project development or project installed as dependency for reactphp/reactphp
(@include __DIR__ . '/../../vendor/autoload.php') || require __DIR__ . '/../../../../autoload.php';

$loop = Loop::get();

$loop->futureTick(function () use ($loop) {
    echo 'b';

    $loop->stop();

    $loop->futureTick(function () {
        echo 'never';
    });
});

echo 'a';

$loop->run();

echo 'c';
