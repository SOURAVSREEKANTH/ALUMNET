FROM php:8.5-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY docker/php.ini /usr/local/etc/php/conf.d/alumniconnect.ini
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html/
COPY docker/railway-entrypoint.sh /usr/local/bin/railway-entrypoint

RUN chmod +x /usr/local/bin/railway-entrypoint \
    && mkdir -p /var/www/html/storage/memories \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod 700 /var/www/html/storage/memories

WORKDIR /var/www/html

EXPOSE 8080

CMD ["/usr/local/bin/railway-entrypoint"]
