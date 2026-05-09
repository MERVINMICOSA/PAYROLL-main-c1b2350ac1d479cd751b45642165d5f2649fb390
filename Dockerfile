# Dockerfile for Render
FROM php:8.2-apache

# Install PostgreSQL PDO extension and other dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo_pgsql pdo_mysql \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Enable Apache modules
RUN a2enmod rewrite \
    && a2enmod headers \
    && a2enmod expires

# Copy all files to Apache document root
COPY . /var/www/html/

# Permissions: chown first, then chmod tree, then storage + php-sessions so 775 is not lost
RUN mkdir -p /var/www/html/storage/php-sessions \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod 775 /var/www/html/storage/php-sessions \
    && chmod -R 775 /var/www/html/api/logs 2>/dev/null || true

# Configure Apache to serve from root and handle .htaccess
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "<Directory /var/www/html>" >> /etc/apache2/apache2.conf \
    && echo "    Options Indexes FollowSymLinks" >> /etc/apache2/apache2.conf \
    && echo "    AllowOverride All" >> /etc/apache2/apache2.conf \
    && echo "    Require all granted" >> /etc/apache2/apache2.conf \
    && echo "</Directory>" >> /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
