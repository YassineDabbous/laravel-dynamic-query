FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    sqlite-dev \
    autoconf \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-install zip pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Install dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Run tests by default
CMD ["vendor/bin/phpunit"]
