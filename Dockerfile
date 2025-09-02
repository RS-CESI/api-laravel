# ---- 1) Builder Composer : produit vendor/ ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction
# (si tu utilises des repos privés: configure ici les auths Composer)

# ---- 2) Runtime PHP + Apache ----
FROM php:8.2-apache

# Extensions PHP nécessaires (ajoute-en si besoin)
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl libpng-dev libonig-dev libxml2-dev \
    mariadb-client libcurl4-openssl-dev \
 && docker-php-ext-install pdo pdo_mysql zip \
 && a2enmod rewrite

# Docroot -> /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copie d'abord les vendors du builder
COPY --from=vendor /app/vendor ./vendor
# Puis le reste du code
COPY . .

# Permissions & caches
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwx storage bootstrap/cache

# OPTION : préparer l’app (clé + caches). Laisse la clé se générer si absente.
RUN php -r "file_exists('.env') || (file_exists('.env.api') && copy('.env.api', '.env')) || true;" \
 && (php artisan key:generate --force || true) \
 && (php artisan config:cache || true) \
 && (php artisan route:cache || true)

EXPOSE 80
CMD ["apache2-foreground"]
