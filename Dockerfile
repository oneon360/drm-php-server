FROM php:8.2-apache

# Copy folder public ke /var/www/html
COPY ./public /var/www/html/

# Copy keys ke lokasi aman
COPY ./keys /var/www/keys/

EXPOSE 80
