FROM node:22.23.3-bookworm-slim AS frontend-deps
WORKDIR /build
COPY frontend/package*.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./

FROM frontend-deps AS frontend
RUN npm run build

FROM frontend-deps AS frontend-check
COPY contracts/ /contracts/

FROM mcr.microsoft.com/playwright:v1.65.1-noble AS e2e
WORKDIR /tests
COPY frontend/package*.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/e2e/ ./e2e/
COPY frontend/playwright.config.ts ./
CMD ["npm", "run", "e2e"]

FROM composer:2 AS composer-cli

FROM php:8.4-cli-bookworm AS php-base
RUN php -r 'exit(in_array("sqlite", PDO::getAvailableDrivers(), true) ? 0 : 1);'
WORKDIR /app

FROM php-base AS php-check
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-cli /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app/backend
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist
COPY backend/ ./

FROM php-base AS backend-autoload
COPY --from=composer-cli /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app/backend
COPY backend/ ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

FROM php-base AS app
COPY --chown=www-data:www-data --from=backend-autoload /app/backend/ backend/
COPY --from=frontend /build/dist/project-desk/browser/ public/
RUN mkdir var && chown -R www-data:www-data var
USER www-data
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "backend/router.php"]
