<?php

$loader = @include __DIR__ . '/../vendor/autoload.php';
if (!$loader) {
    $loader = require __DIR__ . '/../../../../vendor/autoload.php';
}
$loader->addPsr4('React\\Tests\\EventLoop\\', __DIR__);

if (!defined('SIGUSR1')) {
    define('SIGUSR1', 1);
}

if (!defined('SIGUSR2')) {
    define('SIGUSR2', 2);
}
