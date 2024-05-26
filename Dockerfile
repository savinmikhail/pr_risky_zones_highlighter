FROM php:8.3-cli

RUN docker-php-ext-install curl

# Copy the script into the Docker image
COPY analyzer.php /analyzer.php

WORKDIR /app
RUN curl -sS https://getcomposer.org/installer | php
RUN php composer.phar install

# Set the entrypoint for the action
ENTRYPOINT ["php", "/src/analyzer.php"]
