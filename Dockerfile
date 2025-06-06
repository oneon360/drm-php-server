FROM php:8.2-apache

# Aktifkan akses HTTPS (untuk file_get_contents dengan URL)
RUN apt-get update && \
    apt-get install -y ca-certificates && \
    echo "allow_url_fopen=On" > /usr/local/etc/php/conf.d/allow_url_fopen.ini

# Copy source code ke web root
COPY ./public /var/www/html/

# Copy keys ke folder aman
COPY ./keys /var/www/keys/

# Expose port 80
EXPOSE 80
