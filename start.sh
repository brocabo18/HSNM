#!/bin/sh
set -e

PORT=${PORT:-80}
echo "[HSNM] Starting on port $PORT"

# Adjust Apache listening port (Railway assigns a dynamic PORT)
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

echo "[HSNM] Initializing database..."
php /var/www/html/init_db.php || echo "Database init failed, but continuing..."

echo "[HSNM] Apache starting..."
exec apache2ctl -D FOREGROUND
