FROM php:8.0-cli

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt update && apt install -y git unzip
RUN pecl install phalcon mongodb && docker-php-ext-enable phalcon mongodb
