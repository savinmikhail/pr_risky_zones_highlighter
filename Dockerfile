FROM php:8.3-cli

RUN apt-get update && apt-get install -y libcurl
RUN docker-php-ext-install curl

WORKDIR /app

# Copy the script into the Docker image
COPY /src/analyzer.php /src/analyzer.php

# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set the entrypoint for the action
ENTRYPOINT ["php", "/src/analyzer.php"]
