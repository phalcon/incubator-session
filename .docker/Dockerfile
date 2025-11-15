FROM php:8.1-cli

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt update && apt install -y git unzip
RUN CFLAGS="-Wno-incompatible-pointer-types" pecl install phalcon && \
    pecl install mongodb && docker-php-ext-enable phalcon mongodb
