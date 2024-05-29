FROM php:8.3-cli

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files
COPY composer.json composer.lock /app/

# Install PHP dependencies (excluding dev dependencies)
RUN composer install --no-dev --optimize-autoloader

# Copy the application code
COPY src/ /app/src/

# Set the entrypoint for the action
ENTRYPOINT ["php", "/app/src/analyzer.php"]
