FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        gnupg \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        opcache \
        pdo_mysql \
        zip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-shopware.ini

COPY docker/php/entrypoint.sh /usr/local/bin/shopware-entrypoint
ENTRYPOINT ["shopware-entrypoint"]
CMD ["php-fpm"]
