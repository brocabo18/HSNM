FROM php:8.2-apache

# Install PostgreSQL PDO extension and other dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules and fix MPM conflict
# php:8.2-apache needs mpm_prefork — disable others to avoid "More than one MPM loaded" error
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers

# Copy custom Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copy all application files to Apache web root
COPY . /var/www/html/

# Remove deployment-only files from web root
RUN rm -f /var/www/html/Dockerfile \
    /var/www/html/nixpacks.toml \
    /var/www/html/railway.toml \
    /var/www/html/.env.example

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Startup script: make Apache use Railway's $PORT
RUN echo '#!/bin/bash\n\
PORT=${PORT:-80}\n\
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf\n\
exec apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
