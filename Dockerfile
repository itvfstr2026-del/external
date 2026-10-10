FROM php:8.2-apache

# Install system dependencies and PHP extensions (PDO MySQL, ZIP, GD)
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli zip gd \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set Apache working directory
WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html/

# Ensure uploads directories exist and have proper write permissions
RUN mkdir -p /var/www/html/uploads/signatures /var/www/html/uploads/seals \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80

CMD ["apache2-foreground"]
