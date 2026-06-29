#!/bin/sh
set -e

PORT=${PORT:-80}

echo "[HSNM] Configuring Apache on port: $PORT"

# Update ports.conf to listen on Railway's dynamic PORT
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Update VirtualHost in site config
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

echo "[HSNM] Apache configuration updated. Starting server..."

# Start Apache in foreground
exec apache2ctl -D FOREGROUND
