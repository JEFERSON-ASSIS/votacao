# Plenário — PHP 8.2 + Apache, servindo direto a pasta public/.
# Na VPS o código fica em /opt/plenario (bind mount em /var/www/html);
# o COPY abaixo deixa a imagem utilizável também sem o bind mount.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        curl \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring \
    && pecl channel-update pecl.php.net \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-plenario.ini
COPY docker/session-redis.ini /usr/local/etc/php/conf.d/zz-plenario-session.ini

# A raiz do site é public/: .env, app/, logs/ e database/ ficam fora do alcance da web.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && a2enmod rewrite headers \
    && printf '%s\n' 'ServerName plenariovera.com.br' 'ServerTokens Prod' 'ServerSignature Off' > /etc/apache2/conf-available/zz-plenario.conf \
    && a2enconf zz-plenario

WORKDIR /var/www/html
COPY . .
RUN mkdir -p logs public/uploads \
    && chown -R www-data:www-data logs public/uploads

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1/index.php?r=auth/login" > /dev/null || exit 1
