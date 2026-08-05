# ── Production image: FrankenPHP (Caddy + PHP 8.4) with baked-in app ─────────
# Build:  docker build -t olux-cms .
# The same image runs the web app, the queue worker and the scheduler
# (see docker-compose.prod.yml).

# 1 · Front-end assets
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
COPY public ./public
RUN npm run build

# 2 · PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Flux marketplace auth — only needed if composer.lock references flux-pro.
ARG FLUX_USERNAME=""
ARG FLUX_LICENSE_KEY=""
RUN if [ -n "$FLUX_USERNAME" ]; then composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"; fi \
    && composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

# 3 · Runtime
FROM dunglas/frankenphp:php8.4 AS app
RUN install-php-extensions pdo_mysql redis gd zip bcmath pcntl intl exif

ENV SERVER_NAME=":80"
WORKDIR /app

COPY . /app
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build
COPY Caddyfile /etc/caddy/Caddyfile

RUN php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80 443
