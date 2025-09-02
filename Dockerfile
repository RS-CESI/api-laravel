# ---- 1) Builder Composer : installe vendor/ sans scripts ----
FROM composer:2 AS vendor
WORKDIR /app

# Copier uniquement les manifestes pour profiter du cache
COPY composer.json composer.lock ./

# Installer les dépendances PHP sans exécuter les scripts (artisan, etc.)
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction --no-scripts


# ---- 2) Runtime PHP + Apache ----
FROM php:8.2-apache

# Extensions & outils nécessaires (ajoute bcmath, gd, intl, redis si besoin)
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl libpng-dev libonig-dev libxml2-dev \
    mariadb-client libcurl4-openssl-dev \
 && docker-php-ext-install pdo pdo_mysql zip \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# Docroot -> /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copier d'abord les vendors depuis l'étape builder
COPY --from=vendor /app/vendor ./vendor

# Puis copier le reste du code applicatif
COPY . .

# Permissions nécessaires à Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwx storage bootstrap/cache

# Préparation de l'app (sans échouer le build si une étape est facultative)
RUN php -r "file_exists('.env') || (file_exists('.env.api') && copy('.env.api', '.env')) || true;" \
 && (php artisan key:generate --force || true) \
 && (php artisan package:discover || true) \
 && (php artisan config:cache || true) \
 && (php artisan route:cache || true) \
 && (php artisan storage:link || true)

# Port web
EXPOSE 80

# Lancer Apache au foreground
CMD ["apache2-foreground"]
