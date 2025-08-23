FROM php:8.1-alpine

RUN apk add --no-cache git jq moreutils
RUN apk add --no-cache $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.1.5 \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host = 172.19.0.1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer create-project --prefer-dist laravel/laravel example && \
    cd example

WORKDIR /example

COPY . /laravel-scim-server
RUN jq '.repositories=[{"type": "path","url": "/laravel-scim-server"}]' ./composer.json | sponge ./composer.json

RUN composer require arietimmerman/laravel-scim-server @dev && \
    composer require laravel/tinker

RUN touch ./.database.sqlite && \
    echo "DB_CONNECTION=sqlite" >> ./.env && \
    echo "DB_DATABASE=/.database.sqlite" >> ./.env && \
    echo "APP_URL=http://localhost:18123" >> ./.env

RUN php artisan migrate && \
    echo "User::factory()->count(100)->create();" | php artisan tinker

CMD php artisan serve --host=0.0.0.0 --port=8000
