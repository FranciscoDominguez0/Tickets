FROM php:8.2-apache

# =========================
# DEPENDENCIAS
# =========================
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# =========================
# COMPOSER
# =========================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# =========================
# EXTENSIONES PHP
# =========================
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    gd \
    exif \
    zip

# =========================
# OPCACHE
# =========================
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini

# =========================
# SEGURIDAD
# =========================
RUN echo "expose_php=Off" > /usr/local/etc/php/conf.d/security.ini && \
    echo "display_errors=Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "log_errors=On" >> /usr/local/etc/php/conf.d/security.ini

# =========================
# APACHE
# =========================
RUN a2enmod rewrite headers deflate

RUN echo "ServerTokens Prod" >> /etc/apache2/apache2.conf && \
    echo "ServerSignature Off" >> /etc/apache2/apache2.conf && \
    echo "<Directory /var/www/>" >> /etc/apache2/apache2.conf && \
    echo "    Options -Indexes" >> /etc/apache2/apache2.conf && \
    echo "    AllowOverride All" >> /etc/apache2/apache2.conf && \
    echo "</Directory>" >> /etc/apache2/apache2.conf && \
    echo "<FilesMatch \"^\.\">" >> /etc/apache2/apache2.conf && \
    echo "    Require all denied" >> /etc/apache2/apache2.conf && \
    echo "</FilesMatch>" >> /etc/apache2/apache2.conf


# =========================
# LIMITE CONEXIONES
# =========================
RUN echo "<IfModule mpm_prefork_module>" >> /etc/apache2/apache2.conf && \
    echo "    StartServers 2" >> /etc/apache2/apache2.conf && \
    echo "    MinSpareServers 2" >> /etc/apache2/apache2.conf && \
    echo "    MaxSpareServers 5" >> /etc/apache2/apache2.conf && \
    echo "    MaxRequestWorkers 10" >> /etc/apache2/apache2.conf && \
    echo "    MaxConnectionsPerChild 0" >> /etc/apache2/apache2.conf && \
    echo "</IfModule>" >> /etc/apache2/apache2.conf

# =========================
# COPIAR PROYECTO
# =========================
COPY . /var/www/html/sistema-tickets

WORKDIR /var/www/html/sistema-tickets

# =========================
# COMPOSER INSTALL
# =========================
RUN composer install --no-dev --optimize-autoloader

# =========================
# DOCUMENT ROOT
# =========================
RUN sed -i 's#/var/www/html#/var/www/html/sistema-tickets#g' \
    /etc/apache2/sites-available/000-default.conf

# =========================
# PERMISOS
# =========================
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
