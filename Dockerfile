FROM php:8.3-cli-alpine

# Set environment variables
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev

# Install PHP extensions
RUN docker-php-ext-configure intl && \
    docker-php-ext-install pdo_pgsql gd bcmath intl zip mbstring

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application code
COPY . /app

# Create necessary directories and set permissions
RUN mkdir -p /app/storage/logs /app/storage/framework/sessions /app/storage/framework/views /app/storage/framework/cache /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache && \
    chown -R www-data:www-data /app

# Install PHP and Node dependencies (no scripts to avoid DB connection during build)
RUN composer install --no-dev --optimize-autoloader --no-scripts
RUN npm install && npm run build

# Expose port
EXPOSE 10000

# Final Start Command: Run migrations and seed for Neon initialization
CMD php artisan migrate --force --seed && \
    php artisan serve --host 0.0.0.0 --port ${PORT:-10000}
