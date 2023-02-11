FROM php:8.1-fpm

ENV DEBIAN_FRONTEND noninteractive

RUN set -eux; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends \
            git \
            sqlite3; \
    rm -rf /var/lib/apt/lists/*