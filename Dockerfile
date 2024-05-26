FROM php:8.3-cli

WORKDIR /app

# Copy the script into the Docker image
COPY src/analyzer.php /app/analyzer.php

# Set the entrypoint for the action
ENTRYPOINT ["php", "/app/analyzer.php"]
