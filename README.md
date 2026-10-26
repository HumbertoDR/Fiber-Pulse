# FiberPulse AI · SENA IAoT Telemetry Dashboard

Sistema de telemetría e IAoT en tiempo real para monitorear métricas de hardware y red del servidor local, con diagnóstico predictivo impulsado por **Google Gemini 3.5 Flash Lite**.

Diseñado como panel de control tipo NOC para instructores y asistentes del SENA, con modo oscuro/claro, gráficos en vivo, código QR de conexión rápida y simulación de anomalías.

## Tabla de Contenidos

- [Arquitectura](#arquitectura)
- [Requisitos](#requisitos)
- [Despliegue Rápido](#despliegue-rápido)
- [Configuración](#configuración)
- [Servicios Docker](#servicios-docker)
- [Funcionalidades](#funcionalidades)
- [Comandos Artisan](#comandos-artisan)
- [Solución de Problemas](#solución-de-problemas)

## Arquitectura

```
                        ┌─────────────────────────────────────────────────┐
                        │              Red Wi-Fi del salón                 │
                        │                                                 │
   Asistentes (QR) ──►  │  Nginx (:80→:8080)                              │
                        │    │                                            │
                        │    ▼                                            │
                        │  PHP-FPM (app)  ──►  MySQL 8 (db)              │
                        │    │                  ▲                         │
                        │    │                  │                         │
                        │    ▼                  │                         │
                        │  Redis ◄──────── Collector (bucle cada 5s)    │
                        │    ▲                  │                         │
                        │    │                  ▼                         │
                        │  Worker ◄──── AnalyzeTelemetryJob               │
                        │    │                  │                         │
                        │    ▼                  ▼                         │
                        │  Gemini 3.5 Flash Lite API  /proc (métricas reales)  │
                        └─────────────────────────────────────────────────┘
```

**Stack tecnológico:**
- **Backend:** Laravel 13 (PHP 8.3), Nginx, PHP-FPM
- **Base de datos:** MySQL 8 + Redis (caché, colas, telemetría en tiempo real)
- **Frontend:** Blade + Tailwind CSS 4 (dark/light mode) + Chart.js + qrcode.js
- **Tiempo real:** Server-Sent Events (SSE) con Nginx sin buffering
- **IA:** Google Gemini 3.5 Flash Lite (Pay-as-you-go) con salida JSON estructurada
- **Colas:** Laravel Queue (Redis) para análisis IA en segundo plano

## Requisitos

- **Docker** 24+ y **Docker Compose** v2+
- Una API key de Google Gemini (obténla gratis en [Google AI Studio](https://aistudio.google.com/apikey))

> Si no tienes API key, el sistema funciona con un análisis basado en umbrales locales (sin IA externa).

## Despliegue Rápido

### 1. Clonar el proyecto

```sh
git clone <repo-url> FiberPulse
cd FiberPulse
```

### 2. Configurar el entorno

```sh
# Copiar el archivo de ejemplo
cp .env.example .env

# Editar .env y agregar tu API key de Gemini
# GEMINI_API_KEY=tu_api_key_aqui
```

### 3. Levantar todos los servicios

```sh
docker-compose up -d --build
```

Esto inicia 6 contenedores:
- `fiberpulse-app` → PHP-FPM (servidor Laravel)
- `fiberpulse-nginx` → Nginx (proxy + SSE)
- `fiberpulse-db` → MySQL 8
- `fiberpulse-redis` → Redis
- `fiberpulse-worker` → Queue worker (procesa análisis IA)
- `fiberpulse-collector` → Collector de telemetría (bucle cada 5s)

### 4. Acceder al dashboard

Abre en el navegador:

```
http://localhost:8080
```

> **Importante para QR:** Para que el código QR funcione con los asistentes, accede desde la **IP local de tu máquina** (ej: `http://192.168.1.50:8080`), no desde `localhost`. Así el QR apuntará a la IP correcta y los estudiantes podrán escanearlo.

## Configuración

### Variables principales del `.env`

| Variable | Descripción | Default |
|---|---|---|
| `GEMINI_API_KEY` | API key de Google Gemini (Pay-as-you-go) | (vacío = modo local) |
| `GEMINI_MODEL` | Modelo de Gemini a usar | `gemini-3.5-flash-lite` |
| `TELEMETRY_MODE` | `auto` (métricas reales + simulación) o `simulated` | `auto` |
| `TELEMETRY_INTERVAL` | Segundos entre capturas de telemetría | `5` |
| `DB_HOST` | Host de MySQL (nombre del contenedor) | `db` |
| `REDIS_HOST` | Host de Redis | `redis` |
| `APP_LOCAL_IP` | IP fija para el QR (opcional, ej: `192.168.1.50`) | (vacío = auto) |

### Obtener una API key de Gemini

1. Ve a [Google AI Studio](https://aistudio.google.com/apikey)
2. Crea una API key
3. Copia la key en `.env`: `GEMINI_API_KEY=AIza...`

## Servicios Docker

| Servicio | Puerto | Función |
|---|---|---|
| nginx | `8080:80` | Servidor web + SSE |
| db | `3306:3306` | MySQL 8 |
| redis | `6379:6379` | Redis |
| app | `9000` | PHP-FPM (interno) |
| worker | — | Procesa colas (análisis IA) |
| collector | — | Captura métricas cada 5s |

### Comandos útiles de Docker

```sh
# Ver logs en vivo
docker-compose logs -f app
docker-compose logs -f collector
docker-compose logs -f worker

# Reiniciar un servicio
docker-compose restart collector

# Detener todo
docker-compose down

# Detener y borrar datos de MySQL
docker-compose down -v

 Reconstruir después de cambios
docker-compose up -d --build
```

## Funcionalidades

### Panel de Control (Dashboard)

- **6 tarjetas de métricas** con LED de estado animado: CPU, RAM, Temperatura, Throughput de red, Latencia, Señal óptica
- **4 gráficos en tiempo real** (Chart.js) que se actualizan vía SSE cada 3-5 segundos
- **Banner de IA** con diagnóstico predictivo y acción recomendada de Gemini
- **Indicador de anomalía activa** con cuenta regresiva
- **Modo oscuro/claro** con persistencia en localStorage
- **Diseño responsive** (móvil, tablet, desktop)

### Conexión por QR

El panel muestra un código QR que apunta a la URL local de la máquina anfitriona. Los asistentes lo escanean y acceden al dashboard desde sus celulares en la red Wi-Fi del salón.

### Panel del Instructor

5 botones para inyectar anomalías simuladas:

| Anomalía | Efecto |
|---|---|
| Alta Temperatura CPU | Temperatura sube a 88-96°C |
| Sobrecarga de CPU | CPU sube a 93-99% |
| Fuga de Memoria RAM | RAM sube a 92-98% |
| Caída de Enlace Óptico | Señal óptica cae a -38 a -45 dBm |
| Congestión de Red | Throughput sube a 900-1100 Mbps, latencia alta |

Al inyectar una anomalía, el sistema automáticamente dispatcha un análisis de IA para diagnosticar la situación.

### Panel Interactivo (Asistentes)

- **Ping de prueba:** Dispara un ping que genera un pico de latencia visible en los gráficos
- **Alerta simulada:** Activa una congestión de red por 15s para observar la reacción de la IA

## Comandos Artisan

```sh
# Ejecutar dentro del contenedor app:
docker-compose exec app php artisan <comando>

# Capturar telemetría manualmente (bucle continuo)
php artisan telemetry:collect
php artisan telemetry:collect --interval=3 --max=100

# Análisis manual de IA (síncrono)
php artisan telemetry:analyze

# Análisis encolado (lo procesa el worker)
php artisan telemetry:analyze --queued

# Ver estado de colas
php artisan queue:monitor
```

## Solución de Problemas

### El dashboard no muestra datos

El collector tarda ~10 segundos en iniciar. Verifica:

```sh
docker-compose logs collector
```

Si no hay datos, fuerza la migración:

```sh
docker-compose exec app php artisan migrate:fresh --force
```

### El análisis IA no aparece

Sin `GEMINI_API_KEY`, el sistema usa análisis basado en umbrales locales. Verifica el estado del worker:

```sh
docker-compose logs worker
```

### El SSE no se conecta

Verifica que Nginx esté corriendo y que la configuración de SSE esté cargada:

```sh
docker-compose restart nginx
```

### El QR no funciona para otros dispositivos

1. Verifica que accedes al dashboard con la IP de tu máquina (no `localhost`)
2. O configura `APP_LOCAL_IP` en `.env` con la IP fija de tu equipo
3. Asegúrate de que el puerto 8080 esté abierto en el firewall

### Error de permisos en storage

```sh
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Recompilar assets del frontend

```sh
docker-compose exec app npm install
docker-compose exec app npm run build
```

---

**FiberPulse AI** · SENA IAoT Telemetry Dashboard · Laravel 13 + Gemini 3.5 Flash Lite + Docker + Redis + SSE
