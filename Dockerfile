# ── Production image: FrankenPHP (Caddy + PHP 8.4) with baked-in app ─────────
# Build:  docker build -t olux-cms .
# The same image runs the web app, the queue worker and the scheduler
# (see docker-compose.prod.yml).

# 1 · PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Flux marketplace auth — only needed if composer.lock references flux-pro.
ARG FLUX_USERNAME=""
ARG FLUX_LICENSE_KEY=""
RUN if [ -n "$FLUX_USERNAME" ]; then composer config http-basic.composer.fluxui.dev "$FLUX_USERNAME" "$FLUX_LICENSE_KEY"; fi \
    && composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

# 2 · Front-end assets (app.css @imports vendor/livewire/flux CSS and
#     @source-scans vendor blade stubs, so vendor must exist during the build)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# 3 · Runtime
FROM dunglas/frankenphp:php8.4 AS app
RUN install-php-extensions pdo_mysql redis gd zip bcmath pcntl intl exif

# Production php.ini + OPcache tuning. The base image ships OPcache enabled
# but with NO php.ini (development defaults: 10k-file cache, 128 MB, no
# realpath cache). The code is baked into the image, so compiled files can be
# trusted for a minute at a time (deploy.sh regenerates bootstrap/cache right
# after `up -d`, which is why timestamps stay validated rather than frozen).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.interned_strings_buffer=32'; \
        echo 'opcache.max_accelerated_files=30000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=60'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
    } > "$PHP_INI_DIR/conf.d/zz-olux.ini"

ENV SERVER_NAME=":80"
WORKDIR /app

COPY . /app
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build
# Both Caddy variants ship as fallbacks; compose bind-mounts the live choice
# over /etc/caddy/Caddyfile (proxy = behind nginx, edge = Caddy owns 80/443).
COPY Caddyfile.proxy /etc/caddy/Caddyfile.proxy
COPY Caddyfile.edge /etc/caddy/Caddyfile.edge
COPY Caddyfile.proxy /etc/caddy/Caddyfile

RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80 443
