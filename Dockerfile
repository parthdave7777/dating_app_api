FROM php:8.2-apache

# Install dependencies for standard PHP projects
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
 && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mysqli mbstring pdo pdo_mysql zip

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Set the working directory
WORKDIR /var/www/html

# Copy your source code into the container
COPY . .

# Set permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
