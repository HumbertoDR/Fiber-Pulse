# 🌐 FiberPulse AI: Guía de Laboratorio Práctico

Esta guía está diseñada para los aprendices del programa **Análisis y Desarrollo de Software (ADSO)** del Centro Nacional Colombo Alemán (SENA). El objetivo es desplegar la plataforma **FiberPulse AI** utilizando Docker, conectar telemetría real del hardware e interactuar con la IA generativa de Google Gemini.

## 🚀 Fase 1: Despliegue Inicial (El Arranque)

Sigue estos pasos dentro de la carpeta del proyecto para poner a funcionar la plataforma en tu equipo:

1. **Preparar Docker:** Asegúrate de que **Docker Desktop** esté abierto y ejecutándose en segundo plano.

2. **Levantar los contenedores:** Abre la terminal integrada de **Visual Studio Code** y ejecuta el siguiente comando para iniciar los servicios (Laravel, MySQL y Redis):

   ```bash
   docker compose up -d
   ```

   > 💡 *Nota: La primera vez puede tardar unos minutos mientras se descargan las imágenes.*

3. **Preparar la Base de Datos:** Una vez que los contenedores estén activos, ejecuta las migraciones para crear las tablas:

   ```bash
   docker compose exec app php artisan migrate --seed
   ```

4. **Verificar el Dashboard:** Abre tu navegador web e ingresa a la siguiente dirección:

   ```text
   http://localhost:8000
   ```

   ¡Ya deberías ver la interfaz oscura de **FiberPulse AI** funcionando en modo simulado!

## 🔌 Fase 2: Telemetría Real y Google Gemini (El Taller)

Ahora conectaremos el sistema a los sensores reales de tu computador y activaremos la Inteligencia Artificial.

### Reto A: Inyectar Datos Reales del Hardware

1. Abre una ventana de **PowerShell como Administrador**.

2. Navega hasta la carpeta `host-agent` dentro de tu proyecto:

   ```powershell
   cd ruta\a\tu\proyecto\Fiber-Pulse\host-agent
   ```

3. Ejecuta el agente de captura de métricas:

   ```powershell
   powershell -ExecutionPolicy Bypass -File metrics-agent.ps1
   ```

   > ✅ **Resultado:** Observa el dashboard en tu navegador; las gráficas de CPU, RAM y Temperatura ahora muestran la carga real de tu PC.

### Reto B: Activar el Cerebro de la IA (Gemini)

1. Ve a [Google AI Studio](https://aistudio.google.com/app/api-keys), inicia sesión con tu cuenta de Google, haz clic en **"Create API Key"** y copia la clave generada.

2. En VS Code, abre el archivo `.env` *(si no existe, duplica `.env.example` y renómbralo a `.env`)*.

3. Busca la variable `GEMINI_API_KEY` y pega tu clave:

   ```env
   GEMINI_API_KEY=tu_clave_api_aqui_12345
   ```

4. Guarda el archivo presionando `Ctrl + S`.

5. En la terminal de VS Code, reinicia los contenedores para aplicar la nueva clave:

   ```bash
   docker compose restart
   ```

   > ✅ **Resultado:** El panel de IA en el dashboard ahora generará diagnósticos reales impulsados por Gemini.

## 🎮 Fase 3: Hackeando el Sistema (Ejercicios Prácticos)

En esta sección modificaremos la configuración y el código base para observar cómo reacciona el sistema en tiempo real.

### Ejercicio 1: Falsificando la Telemetría (Ataque Hacker)

* **Objetivo:** Simular un sobrecalentamiento crítico inyectando datos falsos desde el agente de Windows para engañar al servidor.

**Pasos a seguir:**

1. En VS Code, abre el archivo `host-agent/metrics-agent.ps1` y ubícate exactamente en la **línea 176** (donde se construye el `$payload`).

2. Cambia la variable de temperatura (`temperature = $temp`) por un valor crítico extremo:

   ```powershell
   temperature = 99.9
   ```

3. Guarda el archivo (`Ctrl + S`).

4. Ve a tu ventana de **PowerShell**, detén el script presionando `Ctrl + C` y vuélvelo a ejecutar:

   ```powershell
   powershell -ExecutionPolicy Bypass -File metrics-agent.ps1
   ```

5. **Observa el resultado:** El dashboard se encenderá en rojo crítico inmediatamente y la IA entrará en pánico diagnosticando un riesgo físico inminente.

### Ejercicio 2: El Efecto Matrix (Acelerar la Red)

* **Objetivo:** Ver el poder de Server-Sent Events (SSE) acelerando la recolección y transmisión de datos al máximo (1 segundo).

**Pasos a seguir:**

1. En VS Code, abre el archivo `.env`.

2. Busca la línea `TELEMETRY_INTERVAL=5` y cambia el `5` por `1`:

   ```env
   TELEMETRY_INTERVAL=1
   ```

3. Guarda el archivo (`Ctrl + S`).

4. En la terminal de VS Code, detén y vuelve a levantar los contenedores para que Docker cargue el nuevo intervalo:

   ```bash
   docker-compose down
   docker-compose up -d
   ```

5. **Observa el resultado:** Las gráficas en tiempo real ahora vuelan, actualizándose cada segundo. *(Para volver a la velocidad normal, cambia el valor nuevamente a `5` y repite el paso 4).*

### Ejercicio 3: Cambio de Personalidad de la IA

* **Objetivo:** Modificar el cerebro de Gemini para que el diagnóstico deje de ser técnico y se vuelva muy divertido.

**Pasos a seguir:**

1. En VS Code, abre el archivo `config/gemini.php` y busca la sección `'system_prompt'` (alrededor de la **línea 25**).

2. Reemplaza el texto actual de esa variable por el siguiente:

   ```php
   'system_prompt' => 'Eres un administrador de redes costeño que está en pánico total porque el sistema se cae. Diagnostica el problema usando jerga exagerada y muy coloquial de la costa caribe colombiana, pero debes devolver un JSON estructurado con el estado, el diagnóstico predictivo y la acción recomendada.',
   ```

3. Guarda el archivo (`Ctrl + S`).

4. En la terminal de VS Code, reinicia los contenedores para aplicar la nueva personalidad:

   ```bash
   docker compose restart
   ```

5. **Observa el resultado:** Ve al dashboard, abre el **Panel Administrador**, inyecta la anomalía **"Alta Temperatura de CPU"** y ríete con el diagnóstico que arrojará la IA.