# FiberPulse AI — Host Metrics Agent

Script PowerShell que corre **en el PC anfitrión (fuera de Docker)** y expone las métricas reales del hardware de Windows mediante un endpoint HTTP en `http://localhost:9100/metrics`.

El contenedor Docker lo consume a través de `http://host.docker.internal:9100/metrics`.

## Métricas que expone

| Campo | Fuente Windows |
|---|---|
| `cpu_usage` | `Win32_Processor.LoadPercentage` (WMI) |
| `ram_usage` | `Win32_OperatingSystem` (WMI) |
| `temperature` | `MSAcpi_ThermalZoneTemperature` / estimación por carga |
| `network_throughput` | `Win32_PerfRawData_Tcpip_NetworkInterface` (WMI) |
| `latency_ms` | `Test-Connection` al gateway predeterminado |
| `optical_signal_dbm` | Valor fijo -18.5 dBm (no hay sensor óptico en PC) |

## Cómo iniciarlo

Abre **PowerShell como Administrador** y ejecuta:

```powershell
cd C:\Users\pc\Desktop\FiberPulse\host-agent
powershell -ExecutionPolicy Bypass -File metrics-agent.ps1
```

El agente queda corriendo e imprime las métricas en consola cada vez que el colector las solicita.

Para usar un puerto diferente:

```powershell
powershell -ExecutionPolicy Bypass -File metrics-agent.ps1 -Port 9101
```

Y actualiza `.env`:

```
METRICS_AGENT_PORT=9101
```

## Configurar FiberPulse para usar el agente

En `.env`:

```env
TELEMETRY_MODE=host_agent
METRICS_AGENT_HOST=host.docker.internal
METRICS_AGENT_PORT=9100
```

Reinicia el collector:

```sh
docker compose restart collector
```

## Temperatura del CPU

La temperatura requiere privilegios de administrador para acceder a `MSAcpi_ThermalZoneTemperature`. Si Windows no expone ese sensor (común en equipos de escritorio), el agente estima la temperatura basándose en la carga del CPU (35°C en reposo → 85°C al 100%).

Para temperatura real instala [OpenHardwareMonitor](https://openhardwaremonitor.org/) — el agente lo detecta automáticamente si está corriendo.

## Verificar que el agente funciona

Desde el navegador o PowerShell:

```powershell
Invoke-RestMethod http://localhost:9100/metrics
```

Desde dentro del contenedor:

```sh
docker exec fiberpulse-app-1 sh -c "wget -qO- http://host.docker.internal:9100/metrics"
```
