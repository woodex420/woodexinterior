# Local/dev image for Woodex OS (docs/17 §12). Hostinger does not use it.
# PHP 8.4 CLI with the extensions the application needs (MySQL, dompdf, media library, images).
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl gd zip bcmath exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
