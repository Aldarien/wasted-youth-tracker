FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring \
    && useradd --create-home --uid 1000 app \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
USER app
