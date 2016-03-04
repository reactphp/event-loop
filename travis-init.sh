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

    # install 'pecl-ev' PHP extension
    git clone http://bitbucket.org/osmanov/pecl-ev.git
    # 0.2.12
    pushd pecl-ev
    phpize
    ./configure
    make
    # make test
    make install
    popd
    echo "extension=ev.so" >> "$(php -r 'echo php_ini_loaded_file();')"

    # install 'libev' PHP extension
    git clone --recursive https://github.com/m4rw3r/php-libev
    pushd php-libev
    phpize
    ./configure --with-libev
    make
    make install
    popd
    echo "extension=libev.so" >> "$(php -r 'echo php_ini_loaded_file();')"

fi

composer install --dev --prefer-source
