# EventLoop Component

[![Build Status](https://secure.travis-ci.org/reactphp/event-loop.png?branch=master)](http://travis-ci.org/reactphp/event-loop)

Event loop abstraction layer that libraries can use for evented I/O.

In order for async based libraries to be interoperable, they need to use the
same event loop. This component provides a common `LoopInterface` that any
library can target. This allows them to be used in the same loop, with one
single `run` call that is controlled by the user.

In addition to the interface there are some implementations provided:

* `StreamSelectLoop`: This is the only implementation which works out of the
  box with PHP. It does a simple `select` system call. It's not the most
  performant of loops, but still does the job quite well.

* `LibEventLoop`: This uses the `libevent` pecl extension. `libevent` itself
  supports a number of system-specific backends (epoll, kqueue).

* `LibEvLoop`: This uses the `libev` pecl extension
  ([github](https://github.com/m4rw3r/php-libev)). It supports the same
  backends as libevent.

* `ExtEventLoop`: This uses the `event` pecl extension. It supports the same
  backends as libevent.

All of the loops support these features:

* File descriptor polling
* One-off timers
* Periodic timers
* Deferred execution of callbacks

## Usage

Here is an async HTTP server built with just the event loop.

```php
$loop = React\EventLoop\Factory::create();

$server = stream_socket_server('tcp://127.0.0.1:8080');
stream_set_blocking($server, 0);

$loop->onReadable($server, function ($server, $loop) {
    $conn = stream_socket_accept($server);
    $data = "HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nHi\n";

    $loop->onWritable($conn, function ($conn, $loop) use (&$data) {
        $written = fwrite($conn, $data);
        if ($written === strlen($data)) {
            fclose($conn);
            $loop->remove($conn);
        } else {
            $data = substr($data, 0, $written);
        }
    });

    $loop->enableWrite($conn);
});

$loop->enableRead($server);

$loop->addPeriodicTimer(5, function () {
    $memory = memory_get_usage() / 1024;
    $formatted = number_format($memory, 3).'K';
    echo "Current memory usage: {$formatted}\n";
});

$loop->run();
```

**Note:** The factory is just for convenience. It tries to pick the best
available implementation. Libraries `SHOULD` allow the user to inject an
instance of the loop. They `MAY` use the factory when the user did not supply
a loop.
