FROM php:8.4-cli

RUN apt-get update \
  && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libonig-dev libsqlite3-dev \
  && docker-php-ext-install bcmath pdo_pgsql pgsql pdo_sqlite mbstring zip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
