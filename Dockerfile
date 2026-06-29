FROM php:8.2-apache

# Install PostgreSQL + other PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Set ServerName to suppress FQDN warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy Apache site config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copy app files
COPY . /var/www/html/

# Remove deployment-only files
RUN rm -f /var/www/html/Dockerfile \
          /var/www/html/nixpacks.toml \
          /var/www/html/railway.toml \
          /var/www/html/.env.example \
          /var/www/html/start.sh

# Fix permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Write start script directly in Dockerfile (avoids Windows \r\n line ending issues)
RUN printf '#!/bin/sh\nPORT=${PORT:-80}\necho "[HSNM] Starting on port $PORT"\nsed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\nsed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf\nexec apache2-foreground\n' > /start.sh \
    && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
