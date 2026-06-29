FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# ── Install Apache + PHP from Debian repos ─────────────────────────────────
# Using debian packages instead of php:8.2-apache because the official image
# has a persistent MPM conflict. The libapache2-mod-php8.2 Debian package
# automatically runs a2dismod mpm_event && a2enmod mpm_prefork in its
# postinstall script — guaranteed correct MPM configuration.
RUN apt-get update && apt-get install -y --no-install-recommends \
        apache2 \
        php8.2 \
        php8.2-pgsql \
        php8.2-mbstring \
        php8.2-zip \
        php8.2-curl \
        php8.2-xml \
        libapache2-mod-php8.2 \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

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
exec apache2ctl -D FOREGROUND\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
