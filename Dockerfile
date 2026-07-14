# Método Kiru — imagen para Railway (PHP 8.2 + Apache)
FROM php:8.2-apache

# Extensión de MySQL (PDO) que usa la app
RUN docker-php-ext-install pdo pdo_mysql

# Deja UN ÚNICO MPM cargado (evita "More than one MPM loaded").
# Se manipulan los enlaces directamente para no depender del orden de a2enmod/a2dismod.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
 && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
 && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
 && a2enmod rewrite

# Comprobación en tiempo de build: aborta si queda más de un MPM enlazado
RUN test "$(ls /etc/apache2/mods-enabled/mpm_*.load | wc -l)" = "1"

# Copia el proyecto al directorio web
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Script que ajusta Apache al puerto que asigna Railway ($PORT) y arranca
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]
