# Use official PHP Apache image
FROM php:8.2-apache

# Enable mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy application files to the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Ensure Apache serves from the correct directory (optional, default is /var/www/html)
# If your files are not directly in the root, adjust the DocumentRoot in Apache config.
# For most setups, the default is fine.

# Expose port 80
EXPOSE 80