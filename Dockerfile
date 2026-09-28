FROM php:8.3-apache

# O PHP com Apache precisa usar apenas um MPM.
# Algumas versões da imagem base podem deixar mais de um MPM habilitado.
RUN a2dismod mpm_event mpm_worker mpm_worker2 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite headers \
    && docker-php-ext-install pdo_mysql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && rm -f /var/www/html/backend/api/.instalado

EXPOSE 80
