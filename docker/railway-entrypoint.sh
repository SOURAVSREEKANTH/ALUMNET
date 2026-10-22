#!/bin/sh
set -eu

port="${PORT:-8080}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a valid numeric TCP port." >&2
        exit 1
        ;;
esac

sed -i "s/:8080>/:${port}>/" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/^Listen 8080$/Listen ${port}/" /etc/apache2/ports.conf

mkdir -p /var/www/html/storage/memories
chown -R www-data:www-data /var/www/html/storage
chmod 700 /var/www/html/storage/memories

exec apache2-foreground
