FROM php:8.3-cli

# Servidor PHP simples para o Railway.
# Usamos o servidor embutido do PHP para evitar conflitos de MPM do Apache.
RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
