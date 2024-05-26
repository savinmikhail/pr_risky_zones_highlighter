FROM php:8.3-cli

# Install any dependencies
# RUN docker-php-ext-install 

# Copy the script into the Docker image
COPY analyzer.php /analyzer.php

# Install Composer and any PHP dependencies
COPY composer.json composer.lock /app/
WORKDIR /app
RUN curl -sS https://getcomposer.org/installer | php
RUN php composer.phar install

# Set the entrypoint for the action
ENTRYPOINT ["php", "/analyzer.php"]
