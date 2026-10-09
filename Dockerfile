# Seguridad Minera — PHP 8.3 + Apache + PostgreSQL (pdo_pgsql) para Railway
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo_pgsql gd \
 && rm -rf /var/lib/apt/lists/*

# Límites de subida para fotos y zona horaria
RUN { echo 'upload_max_filesize=12M'; echo 'post_max_size=60M'; echo 'max_file_uploads=20'; \
      echo 'date.timezone=America/Argentina/San_Juan'; echo 'expose_php=Off'; } > /usr/local/etc/php/conf.d/app.ini

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod headers rewrite

WORKDIR /var/www/app
COPY . /var/www/app
RUN chmod +x docker/entrypoint.sh

ENV PORT=8080
EXPOSE 8080
CMD ["docker/entrypoint.sh"]
