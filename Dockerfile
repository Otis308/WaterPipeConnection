FROM php:8.3-apache

LABEL maintainer="Senior PHP Dev & Ph.D AI Engineering - HUIT Open Source Course"

RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    default-mysql-client \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite mysqli gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite headers

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . /var/www/html

RUN sed -i 's/\r$//' /var/www/html/docker/entrypoint.sh \
    && chmod +x /var/www/html/docker/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
