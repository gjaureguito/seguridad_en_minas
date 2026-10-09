#!/bin/sh
set -e
# Railway asigna el puerto en $PORT
PORT="${PORT:-8080}"
export PORT
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
php /var/www/app/bin/migrate.php
exec apache2-foreground
