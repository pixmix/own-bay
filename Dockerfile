FROM php:8.2-apache
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
RUN echo "upload_max_filesize = 20M\npost_max_size = 25M" > /usr/local/etc/php/conf.d/uploads.ini
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override
