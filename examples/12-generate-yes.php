<?php

require __DIR__ . '/../vendor/autoload.php';

// data can be given as first argument or defaults to "y"
$data = (isset($argv[1]) ? $argv[1] : 'y') . "\n";

// repeat data X times in order to fill around 200 KB
$data = str_repeat($data, round(200000 / strlen($data)));

$loop = React\EventLoop\Factory::create();

$stdout = STDOUT;
if (stream_set_blocking($stdout, false) !== true) {
    fwrite(STDERR, 'ERROR: Unable to set STDOUT non-blocking' . PHP_EOL);
    exit(1);
}

$loop->addWriteStream($stdout, function () use ($loop, $stdout, &$data) {
    // try to write data
    $r = fwrite($stdout, $data);

    // nothing could be written despite being writable => closed
    if ($r === 0) {
        $loop->removeWriteStream($stdout);
        fclose($stdout);
        fwrite(STDERR, 'Stopped because STDOUT closed' . PHP_EOL);

        return;
    }

    // implement a very simple ring buffer, unless everything has been written at once:
    // everything written in this iteration will be appended for next iteration
    if (isset($data[$r])) {
        $data = substr($data, $r) . substr($data, 0, $r);
    }
});

$loop->run();
