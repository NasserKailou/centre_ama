# ============================================================
# CSI — Dockerfile multi-stage optimisé
# PHP 8.2 + Nginx + FPM
# ============================================================

# ── Stage 1 : Build des dépendances ──────────────────────
FROM composer:2.7 AS composer-build

WORKDIR /app

# Copier les fichiers de dépendances
COPY composer.json composer.lock ./

# Installer les dépendances de production sans les scripts (plus rapide)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

# Copier le code source
COPY . .

# Exécuter les scripts post-install
RUN composer dump-autoload --optimize --no-dev

# ── Stage 2 : Image finale PHP-FPM + Nginx ───────────────
FROM php:8.2-fpm-alpine AS production

# Metadata
LABEL maintainer="CSI <admin@csi.ne>"
LABEL description="CSI - Centre de Santé Intégré"
LABEL version="1.0.0"

# ── Dépendances système ──
RUN apk add --no-cache \
    nginx \
    supervisor \
    # Extensions PHP dépendances
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    # Pour Dompdf
    libfontconfig1 \
    # Utilitaires
    curl \
    tzdata \
    && rm -rf /var/cache/apk/*

# ── Extensions PHP ──
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && rm -rf /tmp/pear

# ── Configuration PHP ──
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/php.ini          $PHP_INI_DIR/conf.d/99-csi.ini
COPY docker/php/www.conf         /usr/local/etc/php-fpm.d/www.conf

# ── Configuration Nginx ──
COPY docker/nginx/nginx.conf     /etc/nginx/nginx.conf
COPY docker/nginx/csi.conf       /etc/nginx/http.d/default.conf

# ── Configuration Supervisor ──
COPY docker/supervisord.conf     /etc/supervisord.conf

# ── Timezone ──
ENV TZ=Africa/Niamey
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# ── Répertoire application ──
WORKDIR /var/www/html

# Copier les dépendances depuis le stage build
COPY --from=composer-build --chown=www-data:www-data /app/vendor /var/www/html/vendor

# Copier le code source
COPY --chown=www-data:www-data . .

# ── Créer les répertoires nécessaires ──
RUN mkdir -p var/cache var/log public/uploads \
    && chown -R www-data:www-data var public/uploads \
    && chmod -R 775 var public/uploads

# ── Optimisations Symfony pour production ──
RUN APP_ENV=prod php bin/console cache:warmup --env=prod 2>/dev/null || true

# ── Exposer le port ──
EXPOSE 80

# ── Healthcheck ──
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# ── Point d'entrée ──
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
