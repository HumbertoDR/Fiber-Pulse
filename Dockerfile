# syntax=docker/dockerfile:1
FROM php:8.4-cli

# ─── Dependencias del sistema ───
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# ─── Extensiones PHP necesarias ───
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    pcntl \
    bcmath \
    zip \
    intl \
    opcache \
    sockets

# ─── Redis (caché y colas) ───
RUN pecl install redis && docker-php-ext-enable redis

# ─── Composer ───
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ─── Node.js 22 + npm (para Vite build) ───
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# ─── Configuración PHP para SSE (sin buffering) ───
RUN echo "output_buffering = Off\nimplicit_flush = On\nmemory_limit = 512M\nmax_execution_time = 300" \
    > /usr/local/etc/php/conf.d/fiberpulse.ini

# ─── Entrypoint: prepara el proyecto en el primer arranque ───
RUN cat > /usr/local/bin/entrypoint.sh <<'SCRIPT'
#!/bin/sh
set -e
cd /var/www/html

if [ "$RUN_SETUP" = "1" ]; then
  echo "==> Instalando dependencias de Composer..."
  [ ! -f vendor/autoload.php ] && composer install --no-interaction --prefer-dist --optimize-autoloader

  echo "==> Configurando .env..."
  if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
  fi

  echo "==> Instalando dependencias npm..."
  [ ! -d node_modules ] && npm install

  echo "==> Compilando assets con Vite..."
  [ ! -f public/build/manifest.json ] && npm run build
fi

exec "$@"
SCRIPT
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Workers para SSE + peticiones simultáneas (sobreescrito en docker-compose)
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
