#!/bin/sh
set -e
# Railway asigna el puerto en $PORT
PORT="${PORT:-8080}"
export PORT
# mod_php necesita mpm_prefork: Railway puede dejar cargado otro MPM
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
php /var/www/app/bin/migrate.php
exec apache2-foreground
