# syntax=docker/dockerfile:1

# Imagem oficial PHP 8.5 (FPM). O servidor web (Nginx) fica no Docker Compose.
FROM php:8.5-fpm

# UID/GID do usuário do host, para evitar problemas de permissão em bind mounts
ARG UID=1000
ARG GID=1000

# Dependências do sistema (apenas o necessário para compilar extensões e usar o Composer)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        pcntl \
        zip \
        intl

# Composer (binário oficial, multi-stage)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuração PHP voltada para desenvolvimento
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && { \
        echo "memory_limit=512M"; \
        echo "upload_max_filesize=20M"; \
        echo "post_max_size=20M"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.revalidate_freq=0"; \
    } > "$PHP_INI_DIR/conf.d/zz-dev.ini"

# Ajusta o www-data para o mesmo UID/GID do host
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# O código-fonte é montado via volume pelo Docker Compose.
# Após subir o container: composer install
EXPOSE 9000

CMD ["php-fpm"]
