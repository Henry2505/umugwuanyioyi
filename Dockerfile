# Use official PHP with Apache
FROM php:8.3-apache

# Install system dependencies & common PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better caching)
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy the rest of the app
COPY . .

# Apache: set document root to /public if your app uses it (common in modern PHP)
# Remove or comment the next 3 lines if your index.php is in the root
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# Enable mod_rewrite (needed for pretty URLs)
RUN a2enmod rewrite

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

EXPOSE 10000

CMD ["apache2-foreground"]
