FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Configurar Apache para permitir archivos grandes y habilitar rewrite
RUN echo "file_uploads = On\n\
memory_limit = 256M\n\
upload_max_filesize = 100M\n\
post_max_size = 100M\n\
max_execution_time = 600" > /usr/local/etc/php/conf.d/uploads.ini

RUN echo "LimitRequestBody 104857600" >> /etc/apache2/apache2.conf