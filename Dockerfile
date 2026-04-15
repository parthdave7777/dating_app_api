# Use official PHP Apache image
FROM php:8.2-apache

# Install required packages and MySQL/SSL extensions
RUN apt-get update && apt-get install -y libssl-dev && \
    docker-php-ext-install mysqli pdo pdo_mysql

# FIX: Disable all MPMs first, then specifically enable prefork to avoid the "More than one MPM loaded" error
RUN a2dismod mpm_event mpm_worker || true && \
    a2enmod mpm_prefork rewrite headers ssl

# Update Apache config to allow .htaccess
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy all files to the server
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Start Apache
CMD ["apache2-foreground"]
