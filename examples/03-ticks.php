<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$loop->futureTick(function () {
    echo 'b';
});
$loop->futureTick(function () {
    echo 'c';
});
echo 'a';

$loop->run();
