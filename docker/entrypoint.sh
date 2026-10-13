#!/bin/sh
set -e

echo "FiberPulse AI - Iniciando contenedor app..."

# ─── Esperar a que MySQL esté disponible ───
echo "Esperando a MySQL ($DB_HOST:$DB_PORT)..."
until mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    sleep 1
done
echo "MySQL disponible."

# ─── Instalar dependencias de Composer si no existen ───
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Instalando dependencias de Composer..."
    composer install --no-interaction --optimize-autoloader
else
    echo "Dependencias de Composer ya instaladas."
fi

# ─── Instalar dependencias de npm si no existen ───
if [ "$SKIP_NPM" != "true" ] && [ ! -d "node_modules" ]; then
    echo "Instalando dependencias de npm..."
    npm install
fi

# ─── Compilar assets si es necesario ───
if [ "$SKIP_NPM" != "true" ] && [ ! -f "public/build/manifest.json" ]; then
    echo "Compilando assets con Vite..."
    npm run build 2>/dev/null || echo "Aviso: no se pudieron compilar assets (se usará modo dev)"
fi

# ─── Generar APP_KEY si no está configurada ───
php artisan key:generate --force 2>/dev/null || true

# ─── Ejecutar migraciones ───
echo "Ejecutando migraciones..."
php artisan migrate --force 2>/dev/null || echo "Aviso: las migraciones fallaron (puede reintentar manualmente)"

# ─── Limpiar cachés ───
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# ─── Permisos de storage ───
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "Iniciando PHP-FPM..."
exec "$@"
