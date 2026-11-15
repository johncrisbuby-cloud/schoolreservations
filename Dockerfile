FROM php:8.1-apache

# Kopyahin ang lahat ng kasalukuyang files patungo sa Apache document root
COPY . /var/www/html/

# I-enable ang Apache rewrite module at itakda ang log.in.php bilang DirectoryIndex
RUN a2enmod rewrite \
    && echo "DirectoryIndex log.in.php index.php" > /etc/apache2/mods-enabled/dir.conf

# Ayusin ang permissions ng mga file para mabasa ng web server
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
