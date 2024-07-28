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

# Install Supercronic
ARG SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.29
ARG SUPERCRONIC=supercronic-linux-amd64
ARG SUPERCRONIC_SHA1SUM=cd48d45c4b10f3f0bfdd3a57d054cd05ac96812b
RUN curl -fsSLO "$SUPERCRONIC_URL/$SUPERCRONIC" \
    && echo "$SUPERCRONIC_SHA1SUM $SUPERCRONIC" | sha1sum -c - \
    && chmod +x "$SUPERCRONIC" \
    && mv "$SUPERCRONIC" "/usr/local/bin/supercronic"

# Ensure cron directory exists and set up cron
COPY cron-schedule.sh /usr/local/bin/cron-schedule.sh
RUN chmod +x /usr/local/bin/cron-schedule.sh

# Switch to www-data user
USER www-data

CMD ["php-fpm"]