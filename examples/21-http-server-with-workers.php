<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

if (!$loop instanceof \React\EventLoop\ForkableLoopInterface) {
    exit(1);
}

// start TCP/IP server on localhost:8080
// for illustration purposes only, should use react/socket instead
$server = stream_socket_server('tcp://127.0.0.1:8080');
if (!$server) {
    exit(1);
}
stream_set_blocking($server, false);

// Forks a worker process
$createWorker = function () use ($server, $loop)
{
    $id = pcntl_fork();

    if ($id <= 0) {
        // This is the parent or a failure
        return;
    }

    // This is the worker

    // Dispose the existing loop, we'll start from scratch
    $wloop = $loop->recreateForChildProcess();

    // wait for incoming connections on server socket
    $wloop->addReadStream($server, function ($server) use ($wloop) {
        $conn = stream_socket_accept($server);
        $data = "HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nHi\n";
        $wloop->addWriteStream($conn, function ($conn) use (&$data, $wloop) {
            $written = fwrite($conn, $data);
            if ($written === strlen($data)) {
                fclose($conn);
                $wloop->removeWriteStream($conn);
            } else {
                $data = substr($data, $written);
            }
        });
    });

    // Stop the worker loop on the term signal
    $wloop->addSignal(SIGTERM, function() use ($wloop) {
        $wloop->stop();
    });

    $wloop->run();
    exit(0);
};

// Fork two workers
$createWorker();
$createWorker();

$loop->addSignal(SIGCHLD, $createWorker);
$loop->addSignal(SIGTERM, function () use ($loop, $createWorker) {
    // Shutdown
    // Disable restart
    $loop->removeSignal(SIGCHLD, $createWorker);
    posix_kill(-1, SIGTERM);
});

$loop->addPeriodicTimer(5, function () {
    $memory = memory_get_usage() / 1024;
    $formatted = number_format($memory, 3).'K';
    echo "Current memory usage (master): {$formatted}\n";
});

$loop->run();
