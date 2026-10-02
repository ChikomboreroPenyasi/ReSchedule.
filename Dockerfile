FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install required dependencies (including libpq-dev for PostgreSQL / Supabase direct connections)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions: cURL, PDO, PDO MySQL, and PDO PostgreSQL
RUN docker-php-ext-install curl pdo pdo_mysql pdo_pgsql

# Copy project files into Apache document root
COPY . /var/www/html/

EXPOSE 80
