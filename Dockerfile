FROM composer:2.10.3 AS composer
FROM php:8.4.24-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev libcurl4-openssl-dev ca-certificates \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
