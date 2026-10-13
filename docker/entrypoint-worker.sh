#!/bin/sh
set -e

echo "FiberPulse AI - Iniciando worker/collector..."

# ─── Esperar a que MySQL esté disponible ───
echo "Esperando a MySQL ($DB_HOST:$DB_PORT)..."
until mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    sleep 1
done
echo "MySQL disponible."

# ─── Esperar a que Composer esté listo (vendor existe) ───
echo "Esperando dependencias de Composer..."
until [ -f "vendor/autoload.php" ]; do
    sleep 2
done
echo "Dependencias disponibles."

# ─── Pequeña pausa para asegurar que la app ya migró ───
sleep 3

echo "Iniciando comando: $@"
exec "$@"
