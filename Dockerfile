FROM php:8.2-apache

# Install PostgreSQL PDO extension and other dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy custom Apache config
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copy all application files to Apache web root
COPY . /var/www/html/

# Remove Dockerfile and deployment config from web root (not needed at runtime)
RUN rm -f /var/www/html/Dockerfile \
    /var/www/html/nixpacks.toml \
    /var/www/html/railway.toml \
    /var/www/html/.env.example

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
