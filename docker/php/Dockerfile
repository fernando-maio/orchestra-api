FROM php:8.5-fpm

# Arguments
ARG user=orchestra
ARG uid=1000

# Install system dependencies
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

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Get latest Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create system user
RUN useradd -G www-data,root -u $uid -d /home/$user $user || true
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www/html

# Set user
USER $user

# Expose port 9000 for PHP-FPM
EXPOSE 9000

CMD ["php-fpm"]
