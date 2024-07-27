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

# Install Laravel installer globally
RUN composer global require laravel/installer

# Add composer global bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

COPY wb-back .

# Create .env file and set permissions
RUN touch .env && \
    chown www-data:www-data .env && \
    chmod 644 .env

RUN chown -R www-data:www-data .
USER www-data

CMD ["php-fpm"]