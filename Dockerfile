FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies (only needed for Composer)
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (Optimized for Prod)
RUN composer install --no-dev --optimize-autoloader

# Copy Application Source
COPY public ./public

# Set permissions
RUN chown -R www-data:www-data /var/www/html/public