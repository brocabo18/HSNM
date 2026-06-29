#!/bin/sh
set -e

PORT=${PORT:-80}

echo "[HSNM] Configuring Apache on port: $PORT"

sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

echo "[HSNM] Loading Apache environment variables..."
. /etc/apache2/envvars

echo "[HSNM] Creating Apache runtime directories..."
mkdir -p "$APACHE_RUN_DIR" "$APACHE_LOCK_DIR" "$APACHE_LOG_DIR"

echo "[HSNM] Running config test..."
apache2 -t 2>&1

echo "[HSNM] Starting Apache in foreground..."
exec apache2 -D FOREGROUND
