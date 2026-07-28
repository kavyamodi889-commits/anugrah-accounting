FROM php:8.1-apache

# Install MySQL extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set Apache DocumentRoot
COPY . /var/www/html/

# Set ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
