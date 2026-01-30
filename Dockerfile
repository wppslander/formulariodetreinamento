# Stage 1: Build (Instalação de Dependências)
FROM php:8.2-apache as builder

WORKDIR /app

# Instalar dependências necessárias APENAS para o build (Composer)
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Copiar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar arquivos de definição
COPY composer.json composer.lock ./

# Instalar dependências PHP (Sem dev, otimizado)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# ---

# Stage 2: Produção (Imagem Final Limpa)
FROM php:8.2-apache

WORKDIR /var/www/html

# Configurar Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

# Copiar apenas o necessário do Stage 1 (Vendor)
COPY --from=builder /app/vendor ./vendor

# Copiar código fonte da aplicação
COPY public ./public

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html/public && \
    chmod -R 755 /var/www/html/public
