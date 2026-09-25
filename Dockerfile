# C-BAMS on Railway: PHP 8.5 under Apache, with the OCR and PDF binaries the
# app shells out to installed in the image. Because they are here, the queue
# worker runs in this same container instead of on a separate machine.
FROM php:8.5-apache

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        mupdf-tools ghostscript poppler-utils tesseract-ocr tesseract-ocr-eng \
        unzip git \
 && install-php-extensions pdo_mysql gd intl zip bcmath exif opcache \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'upload_max_filesize=64M\npost_max_size=64M\nmemory_limit=512M\n' > "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html

# Dependencies first, so a code-only change reuses this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

CMD ["entrypoint"]
