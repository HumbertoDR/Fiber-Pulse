# syntax=docker/dockerfile:1
FROM php:8.4-cli

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli pdo_pgsql mbstring exif pcntl bcmath gd zip intl opcache sockets

# Redis (colas y caché)
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Node.js 22 + npm (para Vite)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Script de arranque: solo prepara el proyecto si RUN_SETUP=1
RUN <<'EOF'
cat > /usr/local/bin/entrypoint.sh <<'SCRIPT'
#!/bin/sh
set -e
cd /var/www/html

if [ "$RUN_SETUP" = "1" ]; then
  if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
  fi

  if [ -f .env.example ] && [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
  fi

  if [ -f package.json ] && [ ! -d node_modules ]; then
    npm install
  fi

  if [ -f package.json ] && [ ! -f public/build/manifest.json ]; then
    npm run build
  fi
fi

exec "$@"
SCRIPT
chmod +x /usr/local/bin/entrypoint.sh
EOF

WORKDIR /var/www/html

# Varios procesos para que SSE y peticiones simultáneas no se bloqueen
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]