# Gunakan image resmi PHP 8.2 dengan Apache
FROM php:8.2-apache

# Aktifkan modul PHP jika dibutuhkan (tidak wajib di kasus Anda)
# RUN docker-php-ext-install <modul> 

# Salin file PHP dari folder public ke direktori root web Apache
COPY ./public/ /var/www/html/

# Salin file JSON key ke direktori di luar web root
COPY ./keys/ /var/www/keys/

# Beri izin baca ke file kunci
RUN chmod -R 755 /var/www/keys

# Buka port HTTP
EXPOSE 80
