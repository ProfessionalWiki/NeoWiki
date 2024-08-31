FROM php:8.3-cli-alpine

# Install necessary system dependencies
RUN apk add --no-cache \
    git \
    unzip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Set up a non-root user
RUN adduser -D appuser
USER appuser

# Set the entrypoint to php
ENTRYPOINT ["php"]