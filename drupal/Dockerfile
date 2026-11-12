# Imagen oficial; PHP 8.4 satisface Drupal 11 y Commerce 3.
FROM drupal:11.4.12-php8.4-apache-bookworm

USER root
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get -o Acquire::ForceIPv4=true -o Acquire::By-Hash=false -o Acquire::Retries=3 update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev mariadb-client \
    && docker-php-ext-install intl bcmath \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/drupal
COPY drupal/composer.json drupal/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && composer check-platform-reqs --no-dev

COPY drupal/web/modules/custom/ web/modules/custom/
COPY drupal/web/themes/custom/ web/themes/custom/
COPY drupal/config/ config/
COPY drupal/translations/ /opt/balto/translations/
COPY drupal/settings.php web/sites/default/settings.php
COPY scripts/ /opt/balto/scripts/
COPY drupal/php.ini /usr/local/etc/php/conf.d/balto.ini

# Solo los archivos subidos son modificables por el servidor web.
RUN mkdir -p web/sites/default/files config/sync private \
    && php -r 'require "vendor/autoload.php"; foreach (["config/sync", "private"] as $dir) { file_put_contents($dir . "/.htaccess", \Drupal\Component\FileSecurity\FileSecurity::htaccessLines(TRUE)); }' \
    && chown -R www-data:www-data web/sites/default/files \
    && chmod 444 web/sites/default/settings.php \
    && chmod -R a+rX /opt/balto/scripts

EXPOSE 80
CMD ["apache2-foreground"]
