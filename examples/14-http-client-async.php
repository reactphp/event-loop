<?php

use React\EventLoop\Factory;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();

// resolve hostname before establishing TCP/IP connection (resolving DNS is still blocking here)
// for illustration purposes only, should use react/socket or react/dns instead!
$ip = gethostbyname('www.google.com');
if (ip2long($ip) === false) {
    echo 'Unable to resolve hostname' . PHP_EOL;
    exit(1);
}

// establish TCP/IP connection (non-blocking)
// for illustraction purposes only, should use react/socket instead!
$stream = stream_socket_client('tcp://' . $ip . ':80', $errno, $errstr, null, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
if (!$stream) {
    exit(1);
}
stream_set_blocking($stream, false);

// print progress every 10ms
echo 'Connecting';
$timer = $loop->addPeriodicTimer(0.01, function () {
    echo '.';
});

// wait for connection success/error
$loop->addWriteStream($stream, function ($stream) use ($loop, $timer) {
    $loop->removeWriteStream($stream);
    $loop->cancelTimer($timer);

    // check for socket error (connection rejected)
    if (stream_socket_get_name($stream, true) === false) {
        echo '[unable to connect]' . PHP_EOL;
        exit(1);
    } else {
        echo '[connected]' . PHP_EOL;
    }

    // send HTTP request
    fwrite($stream, "GET / HTTP/1.1\r\nHost: www.google.com\r\nConnection: close\r\n\r\n");

    // wait for HTTP response
    $loop->addReadStream($stream, function ($stream) use ($loop) {
        $chunk = fread($stream, 64 * 1024);

        // reading nothing means we reached EOF
        if ($chunk === '') {
            echo '[END]' . PHP_EOL;
            $loop->removeReadStream($stream);
            fclose($stream);
            return;
        }

        echo $chunk;
    });
});

$loop->run();
