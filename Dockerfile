FROM php:8.2-apache

# ── Install system libraries + PHP extensions ──────────────────────────────
# Consolidate into ONE RUN to avoid layer caching issues and ensure ordering
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/* \
    \
    # ── Fix Apache MPM conflict ──────────────────────────────────────────
    # The php:8.2-apache base image enables mpm_event by default, which
    # conflicts with mod_php (requires mpm_prefork). We remove the symlinks
    # directly — a2dismod silently fails so we cannot use it.
    && rm -f \
        /etc/apache2/mods-enabled/mpm_event.conf \
        /etc/apache2/mods-enabled/mpm_event.load \
        /etc/apache2/mods-enabled/mpm_worker.conf \
        /etc/apache2/mods-enabled/mpm_worker.load \
    \
    # ── Ensure mpm_prefork is enabled ───────────────────────────────────
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf \
              /etc/apache2/mods-enabled/mpm_prefork.conf \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load \
              /etc/apache2/mods-enabled/mpm_prefork.load \
    \
    # ── Enable rewrite + headers directly (skip a2enmod) ────────────────
    && ln -sf /etc/apache2/mods-available/rewrite.load \
              /etc/apache2/mods-enabled/rewrite.load \
    && ln -sf /etc/apache2/mods-available/headers.load \
              /etc/apache2/mods-enabled/headers.load \
    && ln -sf /etc/apache2/mods-available/headers.conf \
              /etc/apache2/mods-enabled/headers.conf

# ── Apache config ──────────────────────────────────────────────────────────
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# ── Copy application files ─────────────────────────────────────────────────
COPY . /var/www/html/

# ── Remove deployment-only files from web root ─────────────────────────────
RUN rm -f /var/www/html/Dockerfile \
          /var/www/html/nixpacks.toml \
          /var/www/html/railway.toml \
          /var/www/html/.env.example

# ── Permissions ────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ── Startup: apply Railway's dynamic $PORT to Apache ──────────────────────
RUN printf '#!/bin/sh\n\
PORT=${PORT:-80}\n\
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf\n\
exec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
