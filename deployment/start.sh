#!/bin/sh
set -eu
case "${PORT:-8080}" in *[!0-9]*|'') echo 'Puerto inválido' >&2; exit 1;; esac
php /var/www/html/scripts/setup.php
sed -i "s/Listen 80/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT:-8080}/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
