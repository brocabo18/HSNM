#!/bin/sh

PORT=${PORT:-80}
echo "[HSNM] Starting on port $PORT"

# Update Apache port
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Run the official php:8.2-apache entrypoint (handles MPM, envvars, etc.)
exec apache2-foreground
