# FastLAN Employee Portal - Dockerfile
# Based on official PHP image with Apache removed, using PHP built-in server

FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql mbstring

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create logs directory with proper permissions
RUN mkdir -p /var/www/html/logs && \
    chmod 777 /var/www/html/logs

# Use Docker-specific config if it exists, otherwise use default config
RUN if [ -f config.docker.php ]; then \
        cp config.php config.php.backup && \
        cp config.docker.php config.php; \
    fi

# Expose port 8080
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -r "echo 'OK';" || exit 1

# Start PHP built-in web server
CMD ["php", "-S", "0.0.0.0:8080"]
