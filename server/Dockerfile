FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
