# =============================================================================
# Imagem PHP do Orchestra
#
# Um Dockerfile, dois alvos:
#   --target dev   -> codigo vem por bind mount (docker-compose.yml)
#   --target prod  -> codigo copiado para dentro da imagem (docker-compose.prod.yml)
#
# O estagio `base` concentra as extensoes para que dev e prod nunca divirjam.
# =============================================================================

# ---------------------------------------------------------------------------
# base: PHP + extensoes. Compartilhado por dev e prod.
# ---------------------------------------------------------------------------
FROM php:8.5-fpm AS base

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis extension
# O pecl.php.net entrega downloads truncados com alguma frequencia: o .tgz baixa
# pela metade e a extracao falha com "unable to unpack". Tentamos ate 3 vezes,
# limpando o download parcial entre as tentativas.
RUN set -eux; \
    ok=0; \
    for i in 1 2 3; do \
        if pecl install redis; then ok=1; break; fi; \
        echo "pecl install redis falhou (tentativa ${i}/3); limpando download parcial"; \
        rm -rf /tmp/pear; \
        sleep 5; \
    done; \
    test "$ok" = 1; \
    docker-php-ext-enable redis

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

# ---------------------------------------------------------------------------
# dev: sem codigo na imagem. O compose monta ./ em /var/www/html.
# ---------------------------------------------------------------------------
FROM base AS dev

ARG user=orchestra
ARG uid=1000

RUN useradd -G www-data,root -u $uid -d /home/$user $user || true
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

USER $user

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# vendor: instala as dependencias de producao isoladamente, para que uma
# mudanca em codigo de aplicacao nao invalide o cache do composer install.
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ---------------------------------------------------------------------------
# prod: codigo dentro da imagem, sem bind mount e sem dependencias de dev.
# ---------------------------------------------------------------------------
FROM base AS prod

COPY docker/php/opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache-prod.ini
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-php-prod.ini

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

# --classmap-authoritative: em producao o autoloader nao precisa varrer o disco
# procurando classes que nao estao no classmap.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint-prod.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
