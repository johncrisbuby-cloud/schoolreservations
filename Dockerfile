FROM php:8.1-apache

# Kopyahin ang lahat ng files mula sa repository papunta sa Apache web root
COPY . /var/www/html/

# Bigyan ng tamang read permissions ang lahat ng files at folders
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# I-configure ang Apache para gawing default ang log.in.php kung walang index.php
RUN echo "DirectoryIndex log.in.php index.php" >> /etc/apache2/apache2.conf

EXPOSE 80
