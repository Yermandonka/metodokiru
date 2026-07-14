#!/bin/sh
# Railway asigna un puerto en $PORT; Apache debe escuchar ahí.
set -e

echo ">>> KIRU start.sh EN MARCHA (esta imagen SÍ es la del Dockerfile) <<<"
echo ">>> MPM cargados:"; apache2ctl -M 2>/dev/null | grep -i mpm || echo "  (no se pudo listar)"

PORT="${PORT:-80}"
echo ">>> Apache escuchará en el puerto ${PORT}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
