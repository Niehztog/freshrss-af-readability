# Load pre-built image of PHP (php-cli) and libxml. 
# See https://hub.docker.com/r/fivefilters/php-libxml for supported versions
# Use build.Dockerfile to compile new versions of PHP/libxml

# For reference, default package versions for Ubuntu are:
# Ubuntu 18.04 - php 7.2, libxml 2.9.4
# Ubuntu 20.04 - php 7.4, libxml 2.9.10
# Ubuntu 20.10 - php 7.4, libxml 2.9.10
# Ubuntu 21.04 - php 7.4, libxml 2.9.10
# Ubuntu 21.10 - php 8.0, libxml 2.9.10

ARG PHP_VERSION
ARG LIBXML_VERSION
FROM fivefilters/php-libxml:php-${PHP_VERSION}-libxml-${LIBXML_VERSION}

RUN apt-get update