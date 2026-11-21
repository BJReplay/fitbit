#
# Common PHP runtime
#
FROM php:8.6-fpm-alpine AS base

# Install the PHP extensions required by the application.
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli

WORKDIR /var/www/html


#
# Build application and Composer dependencies
#
FROM base AS build-stage

# Composer can make use of unzip/zip when installing distribution packages.
RUN apk add --no-cache libzip-dev unzip \
    && docker-php-ext-install zip

# Take Composer from the pinned Composer binary image.
COPY --from=composer/composer:2.11.2-bin@sha256:dc89a3717e5424a7b732a4d3aa034e5316bbc09180996d957bc2de6380a230af \
    /composer \
    /usr/local/bin/composer

#
# Copy Composer metadata first.
#
# This lets Docker reuse the Composer layer when application PHP files change
# but composer.json/composer.lock do not.
#
COPY fitbit/composer.json ./
COPY fitbit/composer.lock ./

RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --classmap-authoritative

#
# Now copy the application itself into /var/www/html.
#
COPY fitbit/ ./

#
# Rebuild the authoritative autoloader after application files are present.
#
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative


#
# Final runtime image
#
FROM base AS runtime

WORKDIR /var/www/html

#
# Copy the complete prepared application:
#
#   PHP application files
#   assets
#   Composer metadata
#   vendor/
#
COPY --from=build-stage /var/www/html/ /var/www/html/

CMD ["php-fpm"]