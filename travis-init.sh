#!/bin/bash
set -e
set -o pipefail

# install 'event' and 'ev' PHP extension on PHP 5.4+ only
if [[ "$TRAVIS_PHP_VERSION" != "5.3" ]]; then
    echo "yes" | pecl install event
    echo "yes" | pecl install ev
fi

# install 'libevent' PHP extension on legacy PHP 5 only
if [[ "$TRAVIS_PHP_VERSION" < "7.0" ]]; then
    curl http://pecl.php.net/get/libevent-0.1.0.tgz | tar -xz
    pushd libevent-0.1.0
    phpize
   ./configure
   make
   make install
   popd
   echo "extension=libevent.so" >> "$(php -r 'echo php_ini_loaded_file();')"
fi

# install 'libev' PHP extension on legacy PHP 5 only
if [[ "$TRAVIS_PHP_VERSION" < "7.0" ]]; then
    git clone --recursive https://github.com/m4rw3r/php-libev
    pushd php-libev
    phpize
    ./configure --with-libev
    make
    make install
    popd
    echo "extension=libev.so" >> "$(php -r 'echo php_ini_loaded_file();')"
fi

# install 'libuv' PHP extension on PHP 7+ only
if ! [[ "$TRAVIS_PHP_VERSION" < "7.0" ]]; then
    echo "yes" | pecl install uv-beta
fi
