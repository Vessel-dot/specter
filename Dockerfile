FROM php:8.2-apache

# 1. Instalamos extensiones para PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# 2. Habilitamos mod_rewrite y mod_alias para las rutas
RUN a2enmod rewrite alias

# 3. Configuramos Apache con aliases para nueva estructura de carpetas
#    /specter/api/*       → backend/controllers/
#    /specter/moderador/* → frontend/src/pages/moderador/
#    /specter/assets/*    → assets/
#    /specter/*           → frontend/src/pages/
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
\n\
    # Orden importante: más específicos primero\n\
    Alias /specter/api        /var/www/html/backend/controllers\n\
    Alias /specter/moderador  /var/www/html/frontend/src/pages/moderador\n\
    Alias /specter/assets     /var/www/html/assets\n\
    Alias /specter/vendor     /var/www/html/vendor\n\
    Alias /specter            /var/www/html/frontend/src/pages\n\
\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
\n\
    DirectoryIndex index.php index.html\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# 4. Forzamos los permisos de la carpeta para el usuario de Apache
RUN chown -R www-data:www-data /var/www/html
