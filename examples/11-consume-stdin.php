<?php

use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

if (stream_set_blocking(STDIN, false) !== true) {
    fwrite(STDERR, 'ERROR: Unable to set STDIN non-blocking' . PHP_EOL);
    exit(1);
}

$loop = Factory::create();

$loop->addReadStream(STDIN, function ($stream) use ($loop) {
    $chunk = fread($stream, 64 * 1024);

    // reading nothing means we reached EOF
    if ($chunk === '') {
        $loop->removeReadStream($stream);
        return;
    }

    echo strlen($chunk) . ' bytes' . PHP_EOL;
});

$loop->run();
