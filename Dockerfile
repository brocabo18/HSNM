FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# Set Apache environment variables directly — avoids sourcing /etc/apache2/envvars at runtime
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log/apache2
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid
ENV APACHE_CONFDIR=/etc/apache2

# ── Install Apache + PHP ───────────────────────────────────────────────────
# libapache2-mod-php8.2 postinstall automatically switches MPM to prefork
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
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ── Pre-create Apache runtime directories ──────────────────────────────────
RUN mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2

# ── Apache config ──────────────────────────────────────────────────────────
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# ── Copy app files ─────────────────────────────────────────────────────────
COPY . /var/www/html/

RUN rm -f /var/www/html/Dockerfile \
          /var/www/html/nixpacks.toml \
          /var/www/html/railway.toml \
          /var/www/html/.env.example \
          /var/www/html/start.sh

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ── Startup: adjust PORT and launch Apache ─────────────────────────────────
RUN printf '#!/bin/sh\n\
PORT=${PORT:-80}\n\
echo "[HSNM] Port: $PORT"\n\
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf\n\
exec apache2ctl -D FOREGROUND\n\
' > /start.sh \
    && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
