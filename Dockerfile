FROM php:8.2-apache

# ติดตั้ง MySQL driver
RUN docker-php-ext-install pdo pdo_mysql mysqli

# เปิด Apache rewrite
RUN a2enmod rewrite

# copy project
COPY . /var/www/html/

# permission
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80