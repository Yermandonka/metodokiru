#!/bin/sh
# Sirve la app con el servidor web integrado de PHP en el puerto de Railway ($PORT).
set -e

PORT="${PORT:-8080}"
echo ">>> KIRU: sirviendo con PHP built-in server en el puerto ${PORT}"

# Límites de subida ampliados para los PDF del mentor (juego de inglés)
exec php -d upload_max_filesize=10M -d post_max_size=12M -S 0.0.0.0:"${PORT}" -t /var/www/html
