#!/bin/sh
# Sirve la app con el servidor web integrado de PHP en el puerto de Railway ($PORT).
set -e

PORT="${PORT:-8080}"
echo ">>> KIRU: sirviendo con PHP built-in server en el puerto ${PORT}"

exec php -S 0.0.0.0:"${PORT}" -t /var/www/html
