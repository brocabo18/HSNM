FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# Apache runtime environment variables
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log/apache2
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid
ENV APACHE_CONFDIR=/etc/apache2

# ── Install Apache + PHP ────────────────────────────────────────────────────
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

# ── Apache virtual host config ─────────────────────────────────────────────
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# ── Copy app files ──────────────────────────────────────────────────────────
COPY . /var/www/html/

# ── Copy and install the startup script ────────────────────────────────────
COPY start.sh /start.sh
RUN sed -i 's/\r//' /start.sh && chmod +x /start.sh

# ── Remove files that should not be served ─────────────────────────────────
RUN rm -f /var/www/html/Dockerfile \
          /var/www/html/nixpacks.toml \
          /var/www/html/railway.toml \
          /var/www/html/.env.example \
          /var/www/html/start.sh

# ── Permissions ─────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["/start.sh"]
