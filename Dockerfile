FROM php:8.1-apache

# Kopyahin ang lahat ng files sa web root ng Apache
COPY . /var/www/html/

# Palitan ang default DirectoryIndex ng Apache para basahin ang log.in.php una sa lahat
RUN echo "DirectoryIndex log.in.php index.php index.html" > /etc/apache2/mods-enabled/dir.conf

# Bigyan ng tamang pahintulot ang mga files
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
