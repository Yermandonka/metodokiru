# Método Kiru — imagen para Railway (PHP 8.2 + Apache)
FROM php:8.2-apache

# Extensión de MySQL (PDO) que usa la app
RUN docker-php-ext-install pdo pdo_mysql

# Módulo de reescritura de Apache (por si se usa más adelante)
RUN a2enmod rewrite

# Copia el proyecto al directorio web
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Script que ajusta Apache al puerto que asigna Railway ($PORT) y arranca
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
