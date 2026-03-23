# Multi-stage Dockerfile for BondKonnect Backend (FrankenPHP)

# Build Stage: Composer and NPM
FROM dunglas/frankenphp:1.4-php8.3-alpine AS build

# Set environment variables
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install system dependencies
RUN apk add --no-cache \
    bash \
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Install PHP extensions
RUN install-php-extensions \
    pcntl \
    gd \
    bcmath \
    intl \
    zip \
    opcache \
    pdo_pgsql \
    redis

# Set working directory
WORKDIR /app

# Copy application code
COPY . /app

# Install dependencies (no scripts to avoid DB connection during build)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
RUN npm install && npm run build

# Production Stage: Final Image
FROM dunglas/frankenphp:1.4-php8.3-alpine AS production

# Install essential runtime tools
RUN apk add --no-cache bash

# Install production PHP extensions
RUN install-php-extensions \
    pcntl \
    gd \
    bcmath \
    intl \
    zip \
    opcache \
    pdo_pgsql \
    redis

# Set working directory
WORKDIR /app

# Copy built application from build stage
COPY --from=build /app /app

# Configure FrankenPHP - Railway injects $PORT dynamically
ENV SERVER_NAME=:${PORT:-8080}

# Copy entrypoint script from the already copied application code
RUN cp /app/docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set correct permissions
RUN chown -R www-data:www-data /app

# Expose port dynamically
EXPOSE ${PORT:-8080}

# Entrypoint for the application
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start FrankenPHP with dynamic port
CMD ["sh", "-c", "frankenphp php-server --root /app/public/ --listen :${PORT:-8080}"]
