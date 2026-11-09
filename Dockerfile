# syntax=docker/dockerfile:1

# ---------- build the front-end ----------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------- application image ----------
FROM php:8.4-cli-alpine

# ffmpeg generates DM video posters; the rest are Laravel's usual extensions
RUN apk add --no-cache \
        bash git curl unzip ffmpeg icu-dev libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pdo_mysql gd intl zip bcmath opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# composer first, so the layer caches until dependencies change
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# The app must start as a server. Migrations are run by the platform's release step.
EXPOSE 8080

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]
