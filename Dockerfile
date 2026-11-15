FROM php:8.1-apache

# I-install ang mysqli extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/
RUN a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
