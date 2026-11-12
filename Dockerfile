# syntax=docker/dockerfile:1

# ══════════════════════════════════════════════════
# ETAPA 1 — Node.js: Compilación de assets
# ══════════════════════════════════════════════════
FROM node:22-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources/ resources/
COPY vite.config.js ./
COPY public/ public/
RUN npm run build

# ══════════════════════════════════════════════════
# ETAPA 2 — PHP Alpine: Producción ultraligera
# ══════════════════════════════════════════════════
FROM php:8.4-cli-alpine AS app

WORKDIR /var/www/html

# ─── Script para instalar extensiones en segundos ───
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# ─── Dependencias del sistema y extensiones precompiladas ───
RUN apk add --no-cache git curl zip unzip mysql-client \
    && install-php-extensions pdo_mysql mbstring pcntl bcmath zip intl opcache sockets redis

# ─── Configuración PHP crítica para SSE ───
RUN echo -e "output_buffering = Off\nimplicit_flush = On\nmemory_limit = 512M\nmax_execution_time = 300\nopcache.enable = 1\nopcache.memory_consumption = 128\nopcache.max_accelerated_files = 10000" \
    > /usr/local/etc/php/conf.d/fiberpulse.ini

# ─── Composer y Assets ───
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY --from=frontend /app/public/build ./public/build

# ─── Entrypoint ───
RUN cat > /usr/local/bin/entrypoint.sh <<'SCRIPT'
#!/bin/sh
set -e
cd /var/www/html

if [ "$RUN_SETUP" = "1" ]; then
  # 1) Crear el .env si no existe
  [ -f .env ] || cp .env.example .env

  # 2) Instalar dependencias si falta vendor
  if [ ! -f vendor/autoload.php ]; then
    echo "==> Instalando dependencias de Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
  fi

  # 3) Generar la APP_KEY si está vacía (exista o no el .env)
  if ! grep -q '^APP_KEY=base64' .env; then
    echo "==> Generando APP_KEY..."
    php artisan key:generate --force
  fi

  # 4) Migrar; sembrar datos solo si la BD está vacía
  echo "==> Preparando base de datos..."
  if php artisan migrate:status >/dev/null 2>&1; then
    php artisan migrate --force
  else
    php artisan migrate --force --seed
  fi
fi

exec "$@"
SCRIPT
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]