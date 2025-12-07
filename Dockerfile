FROM php:8.2-apache

# Install dependencies and enable GD with FreeType support
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache to serve from the public directory
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN set -eux; \
    sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf; \
    sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html

# Ensure coat resources remain readable by Apache
RUN chown -R www-data:www-data /var/www/html

# The container will listen on port 80 by default through the parent image
