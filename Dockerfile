# Método Kiru — imagen para Railway (PHP 8.2, servidor integrado)
FROM php:8.2-cli

# Extensión de MySQL (PDO) que usa la app
RUN docker-php-ext-install pdo pdo_mysql

# Copia el proyecto
WORKDIR /var/www/html
COPY . /var/www/html/

# Script de arranque (sirve con el servidor web integrado de PHP en $PORT)
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
