FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && rm -f /var/www/html/backend/api/.instalado

EXPOSE 80
