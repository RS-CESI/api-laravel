# Utilise l'image PHP officielle avec Apache et extensions utiles
FROM php:8.2-apache

# Variables d'environnement pour Composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/composer

# Étape : Installation des dépendances système
RUN echo "🛠️ Mise à jour et installation des packages..." && \
    apt-get update && apt-get install -y \
        libzip-dev zip unzip git curl libpng-dev libonig-dev libxml2-dev \
        libmcrypt-dev mariadb-client \
        libcurl4-openssl-dev && \
    echo "✅ Packages installés" && \
    docker-php-ext-install pdo pdo_mysql zip && \
    docker-php-ext-install session

# Étape : Activation de mod_rewrite
RUN echo "🔧 Activation du module rewrite d'Apache..." && \
    a2enmod rewrite && \
    echo "✅ mod_rewrite activé"

# Étape : Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN echo "✅ Composer installé"

# Étape : Copie des fichiers Laravel
COPY . /var/www/html
WORKDIR /var/www/html

# Étape : Configuration Apache
RUN echo "⚙️ Modification du DocumentRoot..." && \
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf && \
    a2enmod rewrite && \
    echo "✅ DocumentRoot mis à jour"

# Étape : Permissions
RUN echo "🔐 Attribution des droits aux fichiers..." && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache && \
    echo "✅ Permissions configurées"

# Étape finale : Commande de démarrage
CMD echo "🚀 Démarrage de l'application..." && \
    cp .env.api .env && \
    php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear && \
    php artisan migrate --seed --force && \
    php artisan cache:clear && \
    php artisan key:generate && \
    echo "✅ Application Laravel prête 🎉" && \
    apache2-foreground
