# 🌐 FiberPulse AI: Guía de Laboratorio Práctico

Esta guía está diseñada para los aprendices del programa Análisis y Desarrollo de Software (ADSO) del Centro Nacional Colombo Alemán (SENA). El objetivo es desplegar la plataforma FiberPulse AI utilizando Docker, conectar telemetría real del hardware e interactuar con la IA generativa de Google Gemini.

---

## 🚀 Fase 1: Despliegue Inicial (El Arranque)

Sigue estos pasos para poner a funcionar el proyecto desde cero en tu equipo:

1. **Clonar el repositorio:** Abre tu terminal (Git Bash o similar) y ejecuta:
   ```bash
   git clone https://github.com/HumbertoDR/Fiber-Pulse.git
   cd Fiber-Pulse
   ```
2. **Preparar Docker:** Asegúrate de que **Docker Desktop** esté abierto y ejecutándose en segundo plano.
3. **Abrir el proyecto:** Abre la carpeta clonada en **Visual Studio Code**.
4. **Levantar los contenedores:** En la terminal integrada de VS Code, ejecuta el siguiente comando para descargar las imágenes e iniciar los servicios (Laravel, MySQL y Redis):
   ```bash
   docker compose up -d
   ```
   *(Nota: La primera vez puede tardar unos minutos en descargar todo).*
5. **Preparar la Base de Datos:** Una vez que los contenedores estén arriba, ejecuta las migraciones para crear las tablas necesarias:
   ```bash
   docker compose exec app php artisan migrate --seed
   ```
6. **Verificar el Dashboard:** Ve a Docker Desktop, busca el contenedor `app` y haz clic en el puerto `8000:8000`, o simplemente abre tu navegador web en `http://localhost:8000`. 
   ¡Ya deberías ver la interfaz oscura de FiberPulse AI (funcionando con datos simulados)!

---

## 🔌 Fase 2: Telemetría Real y Google Gemini (El Taller)

Ahora vamos a conectar el sistema a los datos reales de tu equipo y a activar la Inteligencia Artificial.

### Reto A: Inyectar Datos Reales del Hardware
1. Abre una ventana de **PowerShell como Administrador**.
2. Navega hasta la carpeta del agente de Windows dentro del proyecto:
   ```powershell
   cd ruta\a\tu\proyecto\Fiber-Pulse\host-agent
   ```
3. Ejecuta el script de captura de métricas:
   ```powershell
   powershell -ExecutionPolicy Bypass -File metrics-agent.ps1
   ```
   *¡Observa el dashboard! Las gráficas de CPU, RAM y Temperatura ahora muestran la carga real de tu PC.*

### Reto B: Activar el Cerebro de la IA (Gemini)
1. Ve a [Google AI Studio](https://aistudio.google.com/app/api-keys), inicia sesión con tu cuenta de Google y haz clic en **"Create API Key"**. Copia la clave generada.
2. En VS Code, abre el archivo `.env` (si no existe, renombra `.env.example` a `.env`).
3. Busca la línea que dice `GEMINI_API_KEY=` y pega tu clave allí:
   ```env
   GEMINI_API_KEY=tu_clave_api_aqui_12345
   ```
4. Guarda los cambios (`Ctrl + S`).
5. En la terminal de VS Code, reinicia los contenedores para aplicar la nueva configuración:
   ```bash
   docker compose restart
   ```
   *¡Listo! El banner de la IA en el dashboard ahora generará diagnósticos reales usando Gemini.*

---

## 🎮 Fase 3: Hackeando el Sistema (Ejercicios Prácticos)

En esta sección modificaremos el código base para ver cómo reacciona el sistema en tiempo real. 

### Ejercicio 1: Falsificando la Telemetría (Ataque Hacker)
- **Objetivo:** Simular un sobrecalentamiento crítico inyectando datos falsos desde el agente de Windows para engañar al servidor.
- **Misión:** En VS Code, abre el script `host-agent/metrics-agent.ps1`. Ve a la línea donde se construye el `$payload` (exactamente en la línea **176**).
- **Acción:** Cambia la línea `temperature = $temp` por un valor exagerado, por ejemplo: `temperature = 99.9`. 
- **Resultado:** Guarda el archivo (`Ctrl + S`), ve a tu ventana de PowerShell, detén el script presionando `Ctrl + C` y vuelve a ejecutarlo. Observa cómo el dashboard se enciende en rojo crítico inmediatamente y la IA entra en pánico diagnosticando un riesgo físico inminente.

### Ejercicio 2: El Efecto Matrix (Acelerar la Red)
- **Objetivo:** Ver el poder de Server-Sent Events (SSE) acelerando la recolección de datos al máximo.
- **Misión:** Abre el archivo `.env`. Busca la línea que dice `TELEMETRY_INTERVAL=5`. Cambia ese `5` (segundos) por `1`. 
- **Acción:** Guarda el archivo, abre la terminal de VS Code y ejecuta `docker compose restart`. Observa cómo las gráficas en tiempo real ahora vuelan, actualizándose cada segundo.

### Ejercicio 3: Cambio de Personalidad de la IA
- **Objetivo:** Modificar el cerebro de Gemini para que el diagnóstico deje de ser técnico y se vuelva muy divertido.
- **Misión:** En VS Code, abre el archivo `config/gemini.php`. Busca la sección `'system_prompt'` (alrededor de la línea 25).
- **Acción:** Reemplaza todo el texto de esa variable por lo siguiente: 
  *"Eres un administrador de redes costeño que está en pánico total porque el sistema se cae. Diagnostica el problema usando jerga exagerada y muy coloquial de la costa caribe colombiana, pero debes devolver un JSON estructurado con el estado, el diagnóstico predictivo y la acción recomendada."*
- **Resultado:** Guarda el archivo (`Ctrl + S`), abre la terminal de VS Code y ejecuta `docker compose restart` (vital para aplicar el cambio). Ve al dashboard, abre el Panel Administrador, inyecta la anomalía de "Alta Temperatura de CPU" y ríete con el diagnóstico que te va a arrojar la IA.