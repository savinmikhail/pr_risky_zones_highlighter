FROM php:8.3-cli

WORKDIR /app

# Copy the script into the Docker image
COPY src/analyzer.php /app/analyzer.php
COPY src/comment.txt /app/comment.txt

# Set the entrypoint for the action
ENTRYPOINT ["php", "/app/analyzer.php"]
