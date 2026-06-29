#!/bin/sh
set -e

PORT=${PORT:-80}

echo "[HSNM] Configuring Apache on port: $PORT"

# Update ports.conf to listen on Railway's dynamic PORT
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf

# Update VirtualHost in site config
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

echo "[HSNM] Loading Apache environment variables..."
# apache2 requires these env vars — source envvars explicitly
. /etc/apache2/envvars

echo "[HSNM] Running config test..."
apache2 -t 2>&1 || true

echo "[HSNM] Starting Apache in foreground..."
exec apache2 -D FOREGROUND
