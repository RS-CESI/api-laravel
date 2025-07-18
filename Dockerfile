# Utilise l'image PHP officielle avec Apache et extensions utiles
FROM php:8.2-apache

# Variables d'environnement pour Composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer

# Installe les dépendances système
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl libpng-dev libonig-dev libxml2-dev \
    libmcrypt-dev mariadb-client \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql zip

RUN docker-php-ext-install session

# Active mod_rewrite pour Apache
RUN a2enmod rewrite

# Installe Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copie les fichiers Laravel dans le conteneur
COPY . /var/www/html

# Positionne le dossier comme dossier de travail
WORKDIR /var/www/html

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

# Donne les bons droits (peut varier selon l'OS hôte)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Lance les migrations + seeders à chaque démarrage
CMD cp .env.api .env && php artisan key:generate && php artisan migrate --seed --force && apache2-foreground
