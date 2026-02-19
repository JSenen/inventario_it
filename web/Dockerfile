FROM php:8.2-apache

# Instalamos PDO y PDO_MYSQL
RUN docker-php-ext-install pdo pdo_mysql

# Necesario para .htaccess de laptop-loans/public
RUN a2enmod rewrite
