#!/bin/bash
set -e
set -o pipefail

if [[ "$TRAVIS_PHP_VERSION" != "hhvm" &&
      "$TRAVIS_PHP_VERSION" != "hhvm-nightly" ]]; then

    # install "libevent" (used by 'event' and 'libevent' PHP extensions)
    sudo apt-get install -y libevent-dev

    # install 'event' PHP extension
    echo "yes" | pecl install event

    # install 'libevent' PHP extension
    curl http://pecl.php.net/get/libevent-0.1.0.tgz | tar -xz
    pushd libevent-0.1.0
    phpize
    ./configure
    make
    make install
    popd
    echo "extension=libevent.so" >> "$(php -r 'echo php_ini_loaded_file();')"

    # install 'libev' PHP extension
    git clone --recursive https://github.com/m4rw3r/php-libev
    pushd php-libev
    phpize
    ./configure --with-libev
    make
    make install
    popd
    echo "extension=libev.so" >> "$(php -r 'echo php_ini_loaded_file();')"

    # install 'libuv'
    git clone --recursive --branch v1.0.0-rc2 --depth 1 https://github.com/joyent/libuv
    pushd libuv
    ./autogen.sh && ./configure && make && sudo make install
    popd

    #install 'php-uv'
    git clone --recursive --branch libuv-1.0 --depth 1 https://github.com/steverhoades/php-uv
    pushd php-uv
    phpize && ./configure --with-uv --enable-httpparser && make && sudo make install
    echo "extension=uv.so" >> "$(php -r 'echo php_ini_loaded_file();')"
    popd

fi

composer install --dev --prefer-source
