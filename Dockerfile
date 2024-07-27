FROM php:8.2-fpm

WORKDIR /var/www/wb-back

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git

RUN docker-php-ext-install pdo pdo_pgsql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create the .composer directory and set permissions before switching to www-data user
RUN mkdir -p /var/www/.composer && \
    chown -R www-data:www-data /var/www/.composer

# Install Laravel installer globally for the www-data user
USER www-data
RUN composer global require laravel/installer

# Add composer global bin to PATH for www-data user
ENV PATH="/var/www/.composer/vendor/bin:${PATH}"

USER root
COPY wb-back .

# Create .env file and set permissions
RUN touch .env && \
    chown www-data:www-data .env && \
    chmod 644 .env

# Set permissions for the working directory
RUN chown -R www-data:www-data /var/www/wb-back

# Switch to www-data user
USER www-data

CMD ["php-fpm"]