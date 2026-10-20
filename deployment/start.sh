#!/bin/sh
set -eu
case "${PORT:-8080}" in *[!0-9]*|'') echo 'Invalid PORT' >&2; exit 1;; esac
AUDITORIA_PASSWORD=${AUDITORIA_PASSWORD:-}
if [ -z "${AUDITORIA_USER:-}" ] || [ "${#AUDITORIA_PASSWORD}" -lt 16 ]; then
  echo 'Set AUDITORIA_USER and AUDITORIA_PASSWORD (at least 16 characters)' >&2
  exit 1
fi
mkdir -p "$AUDITORIA_DATA_DIR"
chown www-data:www-data "$AUDITORIA_DATA_DIR"
chmod 700 "$AUDITORIA_DATA_DIR"
sed -i "s/Listen 80/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/\*:80/*:${PORT:-8080}/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
