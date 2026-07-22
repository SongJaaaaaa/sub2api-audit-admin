FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl libsqlite3-dev unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath pdo_sqlite opcache \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/000-default.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/audit.conf \
    && a2enconf audit

COPY audit.ini /usr/local/etc/php/conf.d/audit.ini
COPY entrypoint.sh /usr/local/bin/audit-entrypoint
RUN chmod +x /usr/local/bin/audit-entrypoint

ENTRYPOINT ["audit-entrypoint"]
CMD ["apache2-foreground"]
