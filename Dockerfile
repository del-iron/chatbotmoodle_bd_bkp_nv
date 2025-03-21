# Use a imagem oficial do PHP com Apache
FROM php:8.1-apache

# Instale extensões necessárias do PHP
RUN docker-php-ext-install pdo pdo_mysql

# Copie o código da aplicação para o diretório padrão do Apache
COPY src/ /var/www/html/

# Defina o diretório de trabalho
WORKDIR /var/www/html/
