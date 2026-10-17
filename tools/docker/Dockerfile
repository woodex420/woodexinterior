# Development/test only. Not the Hostinger production deployment.
FROM composer:2 AS composer
FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libonig-dev libsqlite3-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql pdo_sqlite intl mbstring bcmath zip gd pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /workspace/platform
ENTRYPOINT ["sh", "/workspace/tools/docker/test.sh"]
