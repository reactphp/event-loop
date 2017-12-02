<?php

use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

if (!defined('STDIN') || stream_set_blocking(STDIN, false) !== true) {
    fwrite(STDERR, 'ERROR: Unable to set STDIN non-blocking (not CLI or Windows?)' . PHP_EOL);
    exit(1);
}

$loop = Factory::create();

// read everything from STDIN and report number of bytes
// for illustration purposes only, should use react/stream instead
$loop->addReadStream(STDIN, function ($stream) use ($loop) {
    $chunk = fread($stream, 64 * 1024);

    // reading nothing means we reached EOF
    if ($chunk === '') {
        $loop->removeReadStream($stream);
        stream_set_blocking($stream, true);
        fclose($stream);
        return;
    }

    echo strlen($chunk) . ' bytes' . PHP_EOL;
});

$loop->run();
