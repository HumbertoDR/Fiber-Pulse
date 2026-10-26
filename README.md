# FiberPulse AI — Plataforma de Telemetría e IAoT en Tiempo Real

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.0-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES2024-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Gemini API](https://img.shields.io/badge/Gemini_API-3.5_Flash_Lite-4285F4?style=for-the-badge&logo=google&logoColor=white)
![SSE](https://img.shields.io/badge/SSE-Real--Time-00C853?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=for-the-badge&logo=redis&logoColor=white)
![License](https://img.shields.io/badge/Licencia-MIT-green?style=for-the-badge)

---

**Proyecto de Transferencia Tecnológica**
**Centro Nacional Colombo Alemán — SENA Regional Atlántico**
**Programa: Análisis y Desarrollo de Software (ADSO)**

*Misión Internacional: [Chongqing Polytechnic University of Electronic Technology (CQUET)](https://www.cqepc.edu.cn) — Chongqing, China 🇨🇳*

**Título Oficial:** *Telemetría Web IoT: Visualización de Datos en Tiempo Real*

</div>

---

## Descripción General

FiberPulse AI es un dashboard de telemetría de infraestructura de red en tiempo real, diseñado como prototipo funcional para entornos educativos y demostraciones técnicas en contextos de redes de banda ancha, fibra óptica e IoT industrial.

El sistema conecta la infraestructura física (métricas reales del hardware vía agente PowerShell o simulación) con un backend Laravel que las procesa, persiste y transmite al frontend mediante **Server-Sent Events (SSE)**. Cuando se detectan anomalías, el sistema invoca **Google Gemini API** para generar un diagnóstico predictivo en lenguaje natural, con respaldo automático por un sistema local de umbrales cuando la API no está disponible.

La experiencia de usuario incluye modo oscuro/claro, alertas sonoras sintetizadas con **Web Audio API**, vibración háptica en dispositivos móviles (`navigator.vibrate`), acceso por código QR desde la red local, y un panel de administración protegido por PIN para inyectar anomalías simuladas en tiempo real.

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                     FUENTES DE DATOS                            │
│                                                                 │
│  ┌─────────────────────┐    ┌──────────────────────────────┐   │
│  │  Host Metrics Agent  │    │    Simulador de Telemetría    │   │
│  │  (metrics-agent.ps1) │    │  (TelemetryCollectorService) │   │
│  │  PowerShell + WMI    │    │  Modo: simulated / auto      │   │
│  │  CPU · RAM · Temp    │    │  CPU · RAM · Temp · Red      │   │
│  │  Red · Latencia      │    │  Latencia · Señal Óptica     │   │
│  └──────────┬──────────┘    └──────────────┬───────────────┘   │
│             │ http://host.docker.internal    │ (interno)         │
└─────────────┼───────────────────────────────┼───────────────────┘
              │                               │
              ▼                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                  BACKEND — Laravel 13 (Docker)                  │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  TelemetryCollectCommand  (bucle cada 5s)               │   │
│  │  collect() → applyAnomaly() → determineStatus()         │   │
│  │  store() → MySQL + Redis                                │   │
│  └───────────────────────┬─────────────────────────────────┘   │
│                          │ anomalía detectada                    │
│                          ▼ (síncrono) / rutina (cola Redis)     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  GeminiAnalyzerService                                  │   │
│  │  ├─ Gemini 3.5 Flash Lite API (diagnóstico predictivo)  │   │
│  │  └─ ThresholdAnalysis (respaldo sin API / sin clave)    │   │
│  │     → status: OPTIMAL | WARNING | CRITICAL              │   │
│  │     → predicted_failure + recommended_action            │   │
│  └───────────────────────┬─────────────────────────────────┘   │
│                          │ Redis                                 │
│                          ▼                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  TelemetryController — SSE /telemetry/stream            │   │
│  │  Eventos: telemetry · analysis · anomaly · connections  │   │
│  │  Máx. 30 conexiones simultáneas (PHP_CLI_SERVER_WORKERS)│   │
│  └───────────────────────┬─────────────────────────────────┘   │
└──────────────────────────┼──────────────────────────────────────┘
                           │ SSE (text/event-stream)
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│              FRONTEND — Blade + Tailwind CSS 4 + JS             │
│                                                                 │
│  ┌────────────┐  ┌─────────────┐  ┌──────────┐  ┌──────────┐  │
│  │ Métricas   │  │  Banner IA  │  │ Chart.js │  │ Modal QR │  │
│  │ CPU·RAM    │  │  Gemini /   │  │ Gráficos │  │ Acceso   │  │
│  │ Temp·Red   │  │  Threshold  │  │ en vivo  │  │ LAN      │  │
│  │ Lat·Opt    │  │  OPTIMAL /  │  │ CPU·RAM  │  │          │  │
│  │            │  │  WARNING /  │  │ Temp·Red │  │          │  │
│  │            │  │  CRITICAL   │  │          │  │          │  │
│  └────────────┘  └─────────────┘  └──────────┘  └──────────┘  │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Sistema de Alertas (CRITICAL)                          │   │
│  │  ├─ Web Audio API: tono sintetizado ~1.2s (3 pitidos)   │   │
│  │  ├─ navigator.vibrate([300,100,300,100,500])            │   │
│  │  └─ Botón mute persistente (localStorage)               │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Panel Administrador (PIN: protegido)                   │   │
│  │  Inyección de anomalías en tiempo real:                 │   │
│  │  Alta Temp · Sobrecarga CPU · Fuga RAM                  │   │
│  │  Caída Óptica · Congestión de Red                       │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Características Principales

### 📊 Telemetría en Tiempo Real
- **6 métricas monitoreadas**: CPU (%), RAM (%), Temperatura (°C), Throughput de red (Mbps), Latencia (ms) y Señal Óptica (dBm)
- Actualización cada **5 segundos** mediante SSE — sin polling, sin WebSockets
- Historial visual de los últimos **30 puntos** en gráficos Chart.js animados
- LEDs de estado por métrica: normal (verde) / warning (amarillo) / critical (rojo) con efecto pulse
- Umbrales de alerta configurados: CPU >70% warning / >90% critical, Temp >70°C / >85°C, Señal óptica < -25 dBm / < -35 dBm

### 🤖 Diagnóstico Predictivo Híbrido
- **Primario**: Google Gemini 3.5 Flash Lite — análisis de los últimos 20 registros de telemetría, respuesta JSON estructurada con `status`, `predicted_failure` y `recommended_action` en español técnico
- **Respaldo automático**: sistema local de umbrales que opera cuando la API no está disponible o la clave no está configurada — sin downtime del diagnóstico
- Análisis **síncrono inmediato** al detectar anomalías (≤7 segundos desde la detección al diagnóstico visible)
- Análisis de **rutina cada 60 segundos** vía cola Redis/worker

### 🔴 Panel de Administración e Inyección de Anomalías
- Acceso protegido por **PIN numérico** (configurable en `app.js`)
- **5 anomalías simuladas** inyectables en tiempo real:
  | Anomalía | Efecto en métricas |
  |---|---|
  | Alta Temperatura de CPU | Temperatura 88–96°C |
  | Sobrecarga de CPU | CPU 93–99% + Temp >80°C |
  | Fuga de Memoria RAM | RAM 92–98% |
  | Caída de Enlace Óptico | Señal -38/-45 dBm, Latencia 400–800ms, Throughput ~0 |
  | Congestión de Red | Throughput 900–1100 Mbps, Latencia 120–250ms |
- Duración configurable (por defecto 60s, máximo 300s)
- Botón **Detener** para cancelar la anomalía manualmente

### 📱 Experiencia Multidispositivo
- **Código QR** generado dinámicamente con la IP local de la red — permite que cualquier dispositivo en la misma red acceda al dashboard escaneando el QR
- **Alarma sonora** al estado CRITICAL: tono sintetizado de 3 pitidos (Do-Mi-Do, ~1.2s) generado con Web Audio API sin archivos externos
- **Vibración háptica** en móviles: patrón `[300ms, 100ms, 300ms, 100ms, 500ms]` via `navigator.vibrate()`
- Botón **mute** en la barra superior con estado persistente en `localStorage`
- **Modo oscuro/claro** con detección automática de preferencia del sistema

### 🔧 Modos de Captura de Datos
| Modo | Descripción |
|---|---|
| `host_agent` | Lee métricas reales del hardware Windows vía agente PowerShell (WMI) |
| `real` | Lee `/proc/stat` y `/proc/meminfo` directamente (Linux nativo) |
| `auto` | Intenta `host_agent` → `/proc` → simulación (cascada automática) |
| `simulated` | Genera valores sintéticos con variación aleatoria controlada |

---

## Requisitos Previos

### Con Docker (recomendado)
| Herramienta | Versión mínima |
|---|---|
| Docker Desktop | 24.x |
| Docker Compose | 2.x (incluido en Docker Desktop) |

### Sin Docker (desarrollo local)
| Herramienta | Versión mínima |
|---|---|
| PHP | 8.3+ con extensiones: `pdo_mysql`, `mbstring`, `redis`, `pcntl`, `sockets`, `opcache` |
| Composer | 2.x |
| Node.js | 20.x o 22.x |
| npm | 10.x |
| MySQL | 8.0 |
| Redis | 7.x |

---

## Instalación y Configuración

### Opción A — Docker Compose (recomendado)

**1. Clonar el repositorio**
```bash
git clone https://github.com/tu-usuario/fiberpulse-ai.git
cd fiberpulse-ai
```

**2. Configurar el entorno**
```bash
cp .env.example .env
```

Editar `.env` con los valores necesarios (ver tabla de variables más abajo).

**3. Levantar todos los servicios**
```bash
docker compose up --build -d
```

El primer arranque tarda 3–5 minutos mientras instala dependencias y compila los assets. Los servicios que se inician son:

| Servicio | Puerto | Descripción |
|---|---|---|
| `app` | `8000` | Laravel + SSE |
| `db` | `3307` | MySQL 8 |
| `redis` | — | Caché y colas |
| `worker` | — | Procesador de colas (análisis IA rutinario) |
| `collector` | — | Captura de telemetría cada 5s |
| `phpmyadmin` | `8081` | Administración de BD |

**4. Acceder al dashboard**
```
http://localhost:8000
```

---

### Opción B — Instalación Local

**1. Clonar e instalar dependencias**
```bash
git clone https://github.com/tu-usuario/fiberpulse-ai.git
cd fiberpulse-ai

composer install
npm install
```

**2. Configurar entorno**
```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` y configurar las variables de base de datos y Redis.

**3. Migrar la base de datos**
```bash
php artisan migrate
```

**4. Compilar assets**
```bash
# Producción
npm run build

# Desarrollo (con hot-reload)
npm run dev
```

**5. Iniciar servicios** (en terminales separadas)
```bash
# Servidor web
php artisan serve --host=0.0.0.0 --port=8000

# Worker de colas
php artisan queue:work --tries=3 --timeout=60

# Collector de telemetría
php artisan telemetry:collect
```

---

## Variables de Entorno

Todas las variables están documentadas en `.env.example`. Las más importantes:

```env
# ─── Aplicación ───
APP_URL=http://localhost:8000        # URL pública del dashboard (usada para el QR)
APP_DEBUG=true

# ─── Base de datos ───
DB_CONNECTION=mysql
DB_HOST=db                           # nombre del servicio en docker-compose
DB_PORT=3306
DB_DATABASE=fiberpulse
DB_USERNAME=sail
DB_PASSWORD=secret

# ─── Redis ───
REDIS_HOST=redis
REDIS_PORT=6379

# ─── Google Gemini API ───
# Obtén tu clave en: https://aistudio.google.com/apikey
# Si se deja vacío, el sistema opera en modo de análisis local por umbrales
GEMINI_API_KEY=                      # tu clave de API aquí
GEMINI_MODEL=gemini-3.5-flash-lite
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_TIMEOUT=30

# ─── Telemetría ───
# host_agent | real | auto | simulated
TELEMETRY_MODE=simulated
TELEMETRY_INTERVAL=5

# ─── Agente de métricas del host (solo si TELEMETRY_MODE=host_agent) ───
METRICS_AGENT_HOST=host.docker.internal
METRICS_AGENT_PORT=9100
```

> **Nota sobre `APP_URL`**: Si planeas compartir el dashboard en una red local (Wi-Fi del salón), configura esta variable con la IP real de tu máquina: `APP_URL=http://192.168.1.X:8000`. El código QR se generará con esa dirección automáticamente.

---

## Agente de Métricas del Host (Windows)

Para mostrar métricas **reales** del hardware en lugar de datos simulados, ejecuta el agente PowerShell en el equipo anfitrión (fuera de Docker):

**1. Abrir PowerShell como Administrador**

**2. Ejecutar el agente**
```powershell
cd .\host-agent\
powershell -ExecutionPolicy Bypass -File metrics-agent.ps1
```

El agente expone `http://localhost:9100/metrics` con las métricas en JSON, que el contenedor Docker consume vía `host.docker.internal:9100`.

**3. Activar el modo en `.env`**
```env
TELEMETRY_MODE=host_agent
```

**4. Reiniciar el collector**
```bash
docker compose restart collector
```

**Verificar que el agente responde:**
```powershell
Invoke-RestMethod http://localhost:9100/metrics
```

| Métrica | Fuente |
|---|---|
| CPU | WMI `Win32_Processor.LoadPercentage` |
| RAM | WMI `Win32_OperatingSystem` |
| Temperatura | `MSAcpi_ThermalZoneTemperature` o estimación por carga |
| Red | WMI `Win32_PerfRawData_Tcpip_NetworkInterface` |
| Latencia | `Test-Connection` al gateway |
| Señal Óptica | Valor de referencia (-18.5 dBm, no hay sensor físico en PC) |

> Para temperatura real en equipos de escritorio, instala [OpenHardwareMonitor](https://openhardwaremonitor.org/) antes de iniciar el agente.

---

## Comandos Artisan Disponibles

```bash
# Iniciar el collector de telemetría manualmente
php artisan telemetry:collect

# Ejecutar un análisis de IA síncrono (diagnóstico inmediato en consola)
php artisan telemetry:analyze

# Ejecutar análisis y enviar a la cola
php artisan telemetry:analyze --queued

# Ver logs en tiempo real
php artisan pail
```

---

## Guía de Demostración en Vivo

Esta guía está diseñada para la **Conferencia de Socialización** (50 asistentes) y el **Taller Práctico** (30 aprendices ADSO).

### Antes de iniciar
1. Asegúrate de que el equipo y los dispositivos de los asistentes están en la **misma red Wi-Fi**
2. Configura `APP_URL=http://TU_IP_LOCAL:8000` en `.env` y reinicia: `docker compose restart app`
3. Verifica que el dashboard carga correctamente en `http://localhost:8000`

### Conectar dispositivos móviles
1. Clic en el botón **"Ver QR"** en la barra superior del dashboard
2. Los asistentes escanean el código QR con la cámara de su celular
3. El dashboard se abre en el navegador del celular — el contador de usuarios en el navbar se actualiza en tiempo real

### Demostración de anomalías (paso a paso)
1. Clic en **"Administrador"** en la barra superior
2. Ingresar el PIN de acceso
3. Seleccionar una anomalía del panel (ej. *Alta Temperatura de CPU*)
4. Observar en **todos los dispositivos conectados** simultáneamente:
   - Las métricas saltan a valores críticos en ≤5 segundos
   - El banner de estado cambia a **CRITICAL** (rojo)
   - Suena la **alarma sonora** (tono de 3 pitidos)
   - Los celulares **vibran** con el patrón de alerta
   - En 6–7 segundos aparece el **diagnóstico de Gemini AI** con la falla predicha y la acción recomendada
5. Clic en **"Detener"** para volver al estado normal

### Para el taller práctico (30 aprendices)
Los aprendices pueden clonar el repositorio y modificar directamente:

- **Umbrales de alerta** → `app/Models/TelemetryLog.php` método `determineStatus()`
- **Frecuencia de captura** → variable `TELEMETRY_INTERVAL` en `.env`
- **Modo de datos** → variable `TELEMETRY_MODE` en `.env` (`simulated` / `host_agent`)
- **Valores de simulación** → `app/Services/TelemetryCollectorService.php` método `simulateMetrics()`
- **Prompt del análisis IA** → `config/gemini.php` campo `system_prompt`

---

## Stack Tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | Laravel Framework | 13.x |
| Lenguaje servidor | PHP | 8.4 |
| Base de datos | MySQL | 8.0 |
| Caché y colas | Redis | 7.x |
| Frontend CSS | Tailwind CSS | 4.0 |
| Gráficos | Chart.js | 4.x |
| Código QR | qrcode.js | 1.5 |
| Build tool | Vite | 8.x |
| IA / LLM | Google Gemini API | 3.5 Flash Lite |
| Tiempo real | Server-Sent Events (SSE) | nativo |
| Audio alertas | Web Audio API | nativo |
| Vibración | Vibration API (`navigator.vibrate`) | nativo |
| Contenedores | Docker + Docker Compose | 24.x / 2.x |
| Agente de métricas | PowerShell + WMI | Windows |

---

## Alineación con el Plan de Transferencia Tecnológica SENA

### 1. Repositorio Oficial en GitHub
El repositorio contiene el prototipo completo y funcional con backend, frontend, Docker Compose y esta guía de instalación, cumpliendo el primer objetivo de transferencia: código abierto disponible para la comunidad académica y técnica.

### 2. Conferencia de Socialización (50 asistentes)
El sistema está diseñado para demostraciones públicas en vivo:
- Hasta **30 dispositivos simultáneos** conectados vía SSE al mismo tiempo
- Acceso por **código QR** sin necesidad de escribir URLs
- Reacción visual, sonora y háptica **sincronizada** en todos los dispositivos al inyectar una anomalía
- Conexión con la experiencia en redes de banda ancha y fibra óptica observada en **CQUET (Chongqing, China)**: visualización de señal óptica (dBm), latencia de enlace y diagnóstico predictivo de fallas

### 3. Taller Práctico "De los datos al dashboard" (30 aprendices ADSO/TI)
El proyecto está construido con tecnologías del currículo ADSO:
- **PHP / Laravel**: rutas, controladores, modelos, migraciones, colas, comandos Artisan
- **JavaScript moderno**: módulos ES2024, Web Audio API, SSE, Chart.js
- **SQL / MySQL**: esquema relacional, consultas con Eloquent ORM
- **Docker**: contenedores, variables de entorno, servicios en red
- **APIs REST**: integración con Google Gemini, endpoints JSON propios
- Los aprendices pueden clonar, modificar parámetros e interactuar con el sistema en una sesión de laboratorio de 2 horas

---

## Estructura del Proyecto

```
fiberpulse-ai/
├── app/
│   ├── Console/Commands/
│   │   ├── TelemetryCollectCommand.php   # Bucle de captura cada 5s
│   │   └── TelemetryAnalyzeCommand.php   # Análisis IA manual
│   ├── Http/Controllers/
│   │   ├── DashboardController.php       # Vista principal + QR URL
│   │   └── TelemetryController.php       # SSE + endpoints REST
│   ├── Jobs/
│   │   └── AnalyzeTelemetryJob.php       # Job de análisis en cola
│   ├── Models/
│   │   ├── TelemetryLog.php              # Métricas + umbrales
│   │   └── AiAnalysis.php                # Diagnóstico IA
│   └── Services/
│       ├── GeminiAnalyzerService.php     # Integración Gemini + threshold
│       └── TelemetryCollectorService.php # Captura + anomalías + simulación
├── resources/
│   ├── js/app.js                         # SSE · Charts · Audio · QR · PIN
│   └── views/dashboard.blade.php        # Vista principal (Blade + Tailwind)
├── config/gemini.php                     # Configuración Gemini API
├── host-agent/
│   ├── metrics-agent.ps1                 # Agente PowerShell (host Windows)
│   └── README.md                         # Guía del agente
├── docker-compose.yml                    # Orquestación de servicios
├── Dockerfile                            # Multi-stage build (Node + PHP)
└── .env.example                          # Plantilla de configuración
```

---

## Licencia

Distribuido bajo la **Licencia MIT**. Ver [`LICENSE`](LICENSE) para más información.

---

<div align="center">

Desarrollado por **Humberto Hernando Díaz Rada**

Centro Nacional Colombo Alemán — SENA Regional Atlántico
Programa: Análisis y Desarrollo de Software (ADSO)

*Proyecto de Transferencia Tecnológica derivado de la Misión Internacional*
*Chongqing Polytechnic University of Electronic Technology (CQUET) — Chongqing, China 🇨🇳*

</div>
