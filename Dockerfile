FROM php:8.5-fpm

# Set working directory inside the container
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    libpq-dev \
    libfreetype6-dev \
    libzip-dev \
    supervisor\
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl posix bcmath gd zip

# Install Redis extension
RUN pecl install redis \
 && docker-php-ext-enable redis

# Install Composer (latest version)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy the supervisor config file
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install Node.js and NPM
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Copy application code
COPY . .

RUN git config --global --add safe.directory /var/www/html

# Install Laravel dependencies (production only)
# RUN composer install --no-dev --optimize-autoloader
# --no-scripts: skip post-autoload-dump's `artisan package:discover` (boots the
# app; without a build-time .env this can fail if a provider queries the DB).
# Not needed at build time — the bind-mounted host vendor/bootstrap/cache take
# over at container runtime anyway.
RUN composer install --no-dev --optimize-autoloader --ignore-platform-req=php+ --no-scripts

# ... after composer install ...
RUN npm install
RUN npm run build

# Set permissions for Laravel (storage and cache)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# --- FIX php-fpm log crash ---
# Override default php-fpm error_log path
RUN sed -i 's|^error_log =.*|error_log = /var/log/php-fpm/error.log|' /usr/local/etc/php-fpm.conf \
 && sed -i 's|^;php_admin_value\[error_log\].*|php_admin_value[error_log] = /var/log/php-fpm/fpm-php.www.log|' /usr/local/etc/php-fpm.d/www.conf

# --- FIX php-fpm slowlog crash ---
# Create log directory and slowlog file
RUN mkdir -p /var/log/php-fpm \
    && touch /var/log/php-fpm/error.log \
    && touch /var/log/php-fpm/slow.log \
    && chown -R www-data:www-data /var/log/php-fpm

# --- OVERRIDE php-fpm pool config with our tuned www.conf ---
COPY conf/www.conf /usr/local/etc/php-fpm.d/www.conf

# --- COPY custom php.ini for upload limits and performance ---
COPY conf/custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Expose PHP-FPM port (used by Nginx)
EXPOSE 9000


# Start PHP-FPM directly (no Supervisor)
# CMD ["php-fpm", "--nodaemonize"]
# Start Supervisor (which will handle both php-fpm and horizon)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
