#
# Common PHP runtime
#
FROM php:8.6-fpm-alpine AS base

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli

WORKDIR /var/www/html

#
# Build Composer dependencies
#
FROM base AS build-stage

RUN apk add --no-cache libzip-dev unzip \
    && docker-php-ext-install zip

COPY --from=composer/composer:2.11.3-bin@sha256:6949de616da9d35172659a292dd91eb7bcce80d28f5a752f3194b2fd1183dfc3 \
    /composer \
    /usr/local/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

# Install vendor into /var/www/vendor with an authoritative classmap
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    COMPOSER_VENDOR_DIR=/var/www/vendor \
    composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --classmap-authoritative

#
# Final production runtime image
#
FROM base AS runtime

WORKDIR /var/www/html

# Copy only the baked vendor directory
COPY --from=build-stage /var/www/vendor /var/www/vendor

CMD ["php-fpm"]
