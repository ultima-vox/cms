FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install -j2 pdo_pgsql mbstring zip \
    && if ! php -r 'exit(extension_loaded("dom") ? 0 : 1);'; then docker-php-ext-install dom; fi \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY site.conf /etc/apache2/sites-available/000-default.conf
RUN printf 'Listen 8086\n' > /etc/apache2/ports.conf \
    && printf 'memory_limit=128M\ndisplay_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/cms-hosting.ini
ENV CMS_DOCUMENT_ROOT=/var/www/cms
