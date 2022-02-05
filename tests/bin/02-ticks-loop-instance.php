<?php

use React\EventLoop\Loop;

// autoload for local project development or project installed as dependency for reactphp/reactphp
(@include __DIR__ . '/../../vendor/autoload.php') || require __DIR__ . '/../../../../autoload.php';

$loop = Loop::get();

$loop->futureTick(function () {
    echo 'b';
});

$loop->futureTick(function () {
    echo 'c';
});

echo 'a';

$loop->run();
