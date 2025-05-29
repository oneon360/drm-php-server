FROM php:8.2-apache

# Copy source code public ke web root
COPY ./public /var/www/html/

# Copy keys ke folder terpisah (aman)
COPY ./keys /var/www/keys/

# Expose port 80
EXPOSE 80
