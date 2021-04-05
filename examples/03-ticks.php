<?php

use React\EventLoop\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::get()->futureTick(function () {
    echo 'b';
});
Loop::get()->futureTick(function () {
    echo 'c';
});
echo 'a';

Loop::get()->run();
