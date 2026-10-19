FROM php:8.4.26-fpm-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl zip opcache apcu bcmath pcov \
    && apk add --no-cache git unzip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-mustang.ini

ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app 2>/dev/null || true \
    && adduser -D -u ${UID} -G $(getent group ${GID} | cut -d: -f1) app 2>/dev/null || true
USER ${UID}:${GID}
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
