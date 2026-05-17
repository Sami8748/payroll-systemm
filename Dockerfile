FROM php:8.2-apache

# ✅ ติดตั้ง MySQL Driver
RUN docker-php-ext-install pdo pdo_mysql mysqli

# ✅ เปิด apache rewrite
RUN a2enmod rewrite

# ✅ copy project เข้า container
COPY . /var/www/html/

# ✅ ตั้ง permission
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80