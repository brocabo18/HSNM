FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

# ── Install Apache + PHP ───────────────────────────────────────────────────
# libapache2-mod-php8.2 postinstall automatically runs:
#   a2dismod mpm_event && a2enmod mpm_prefork
# which is the only reliable way to fix the MPM conflict.
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

# ── Startup script (COPY avoids printf escape issues) ─────────────────────
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
