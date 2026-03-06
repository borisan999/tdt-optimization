# -----------------------------------------------------------------------------
# Stage 1: Frontend (actualmente vacío)
# -----------------------------------------------------------------------------
FROM docker.io/library/node:20-slim AS frontend_builder

# Instalar dependencias del frontend, si las hubiera. Por ahora, este stage está vacío.
# Si en el futuro se añade un package.json, aquí se instalarían las dependencias de npm.
WORKDIR /app/frontend


# -----------------------------------------------------------------------------
# Stage 2: PHP con Apache y Composer
# -----------------------------------------------------------------------------
FROM docker.io/composer/composer:latest-bin AS composer
FROM docker.io/library/php:8.2-apache AS php_apache

# Instalar dependencias del sistema y extensiones de PHP
# Incluir coinor-cbc para el solver de optimización
RUN apt-get update && apt-get install -y \
    unzip \
    zip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    coinor-cbc \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo pdo_mysql

# Instalar Composer
COPY --from=composer /composer /usr/bin/composer

# Copiar el código de la aplicación
WORKDIR /var/www/html
COPY . .

# Instalar dependencias de Composer
RUN composer install --no-dev --optimize-autoloader


# -----------------------------------------------------------------------------
# Stage 3: Python
# -----------------------------------------------------------------------------
FROM docker.io/library/python:3.10-slim AS python_runtime

# Instalar dependencias de Python
RUN pip install pandas pulp


# -----------------------------------------------------------------------------
# Stage 4: Imagen final combinada
# -----------------------------------------------------------------------------
FROM docker.io/library/php:8.2-apache

# Copiar el DocumentRoot de Apache
COPY --from=php_apache /var/www/html /var/www/html

# Copiar las dependencias de Python
COPY --from=python_runtime /usr/local/lib/python3.10/site-packages /usr/local/lib/python3.10/site-packages

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Exponer el puerto 80 para Apache
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Crear directorio de logs de optimización y establecer permisos
RUN mkdir -p /var/www/html/storage/optimization_logs && \
    chown -R www-data:www-data /var/www/html/storage && \
    chmod -R 775 /var/www/html/storage

EXPOSE 80
