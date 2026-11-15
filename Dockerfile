FROM php:8.1-apache

# Kopyahin ang buong project files
COPY . /var/www/html/

# Kopyahin ang custom apache config at i-enable
COPY render-apache.conf /etc/apache2/sites-available/000-default.conf

# Bigyan ng tamang pahintulot
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
