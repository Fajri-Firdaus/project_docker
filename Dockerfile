# Satu Dockerfile dipakai bersama app1-php dan app2-php (compose.yaml).
# Kode tidak di-COPY ke sini -- di-mount sebagai volume lewat compose.yaml.
FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www