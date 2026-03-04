FROM php:7.2.34-apache

# 安裝 mysqli
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli
