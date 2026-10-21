# ============================================================
# FiberPulse AI - Host Metrics Agent
# ============================================================
# Corre en Windows (fuera de Docker).
# Lee CPU, RAM, temperatura y red del host mediante WMI/CIM
# y los expone en http://localhost:9100/metrics (JSON).
#
# Uso:
#   powershell -ExecutionPolicy Bypass -File metrics-agent.ps1
#
# El contenedor Docker lo consume vía host.docker.internal:9100
# ============================================================

param(
    [int]$Port = 9100
)

$ErrorActionPreference = 'SilentlyContinue'

# ─── Estado previo para calcular throughput de red ───
$prevNetBytes  = 0
$prevTimestamp = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()

function Get-CpuUsage {
    try {
        $load = (Get-CimInstance -ClassName Win32_Processor -Property LoadPercentage |
                 Measure-Object -Property LoadPercentage -Average).Average
        return [math]::Round([double]$load, 1)
    } catch {
        return 0.0
    }
}

function Get-RamUsage {
    try {
        $os = Get-CimInstance -ClassName Win32_OperatingSystem -Property TotalVisibleMemorySize, FreePhysicalMemory
        if ($os.TotalVisibleMemorySize -le 0) { return 0.0 }
        $used = $os.TotalVisibleMemorySize - $os.FreePhysicalMemory
        return [math]::Round(($used / $os.TotalVisibleMemorySize) * 100, 1)
    } catch {
        return 0.0
    }
}

# Devuelve @{ value = <double>; source = 'sensor' | 'estimated' }
function Get-CpuTemperature {
    # Método 1: MSAcpi_ThermalZoneTemperature (requiere admin, no siempre disponible)
    try {
        $temp = Get-CimInstance -Namespace "root/wmi" -ClassName MSAcpi_ThermalZoneTemperature -Property CurrentTemperature |
                Select-Object -First 1
        if ($temp -and $temp.CurrentTemperature -gt 0) {
            $celsius = [math]::Round(($temp.CurrentTemperature / 10) - 273.15, 1)
            if ($celsius -ge 10 -and $celsius -le 110) {
                return @{ value = $celsius; source = 'sensor' }
            }
        }
    } catch {}

    # Método 2: OpenHardwareMonitor vía WMI (si está instalado)
    try {
        $sensor = Get-CimInstance -Namespace "root/OpenHardwareMonitor" -ClassName Sensor `
                  -Filter "SensorType='Temperature' AND Name LIKE '%CPU%'" |
                  Select-Object -First 1
        if ($sensor -and $sensor.Value -gt 0) {
            return @{ value = [math]::Round([double]$sensor.Value, 1); source = 'sensor' }
        }
    } catch {}

    # Sin sensor: estimar por carga de CPU (lineal 35 a 85 grados)
    $cpu = Get-CpuUsage
    return @{ value = [math]::Round(35 + ($cpu / 100) * 50, 1); source = 'estimated' }
}

function Get-NetworkThroughput {
    param($PrevBytes, $PrevTs)

    try {
        $now     = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
        $timeSec = ($now - $PrevTs) / 1000.0
        if ($timeSec -le 0) { return @{ mbps = 0.0; bytes = $PrevBytes; ts = $now } }

        $adapters = Get-CimInstance -ClassName Win32_PerfRawData_Tcpip_NetworkInterface |
                    Where-Object { $_.Name -notmatch 'loopback' -and $_.BytesTotalPersec -ne $null }

        $totalBytes = ($adapters | Measure-Object -Property BytesTotalPersec -Sum).Sum
        if ($null -eq $totalBytes) { $totalBytes = 0 }

        $delta = $totalBytes - $PrevBytes
        if ($delta -lt 0) { $delta = 0 }  # counter reset

        $mbps = [math]::Round(($delta * 8) / ($timeSec * 1MB), 3)

        return @{ mbps = [math]::Max(0, $mbps); bytes = $totalBytes; ts = $now }
    } catch {
        return @{ mbps = 0.0; bytes = $PrevBytes; ts = ([DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()) }
    }
}

function Get-NetworkLatency {
    # Ping al gateway predeterminado
    try {
        $gw = (Get-NetRoute -DestinationPrefix "0.0.0.0/0" | Sort-Object RouteMetric | Select-Object -First 1).NextHop
        if ($gw) {
            $ping = Test-Connection -ComputerName $gw -Count 1 -ErrorAction Stop
            $ms = if ($ping.Latency) { $ping.Latency } else { $ping.ResponseTime }
            if ($ms -ne $null) { return [int]$ms }
        }
    } catch {}

    # Fallback: ping a 8.8.8.8
    try {
        $ping = Test-Connection -ComputerName "8.8.8.8" -Count 1 -ErrorAction Stop
        $ms = if ($ping.Latency) { $ping.Latency } else { $ping.ResponseTime }
        if ($ms -ne $null) { return [int]$ms }
    } catch {}

    return 5
}

# ─── Servidor HTTP ───
$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://+:$Port/")

try {
    $listener.Start()
    Write-Host "=============================================="
    Write-Host "  FiberPulse AI - Host Metrics Agent"
    Write-Host "  Escuchando en el puerto $Port (todas las interfaces)"
    Write-Host "  Local:  http://localhost:$Port/metrics"
    Write-Host "  Docker: http://host.docker.internal:$Port/metrics"
    Write-Host "  Ctrl+C para detener"
    Write-Host "=============================================="
} catch {
    Write-Host "ERROR: No se pudo iniciar el servidor HTTP en puerto $Port"
    Write-Host $_.Exception.Message
    Write-Host "Intenta ejecutar como Administrador o cambia el puerto con -Port 9101"
    exit 1
}

# Inicializar estado de red
try {
    $adapters     = Get-CimInstance -ClassName Win32_PerfRawData_Tcpip_NetworkInterface |
                    Where-Object { $_.Name -notmatch 'loopback' -and $_.BytesTotalPersec -ne $null }
    $prevNetBytes = ($adapters | Measure-Object -Property BytesTotalPersec -Sum).Sum
    if ($null -eq $prevNetBytes) { $prevNetBytes = 0 }
} catch { $prevNetBytes = 0 }
$prevTimestamp = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()

# ─── Loop principal ───
while ($listener.IsListening) {
    try {
        $context  = $listener.GetContext()
        $request  = $context.Request
        $response = $context.Response

        $response.ContentType = "application/json"
        $response.AddHeader("Access-Control-Allow-Origin", "*")

        if ($request.HttpMethod -eq "GET" -and $request.Url.AbsolutePath -eq "/metrics") {

            $cpu      = Get-CpuUsage
            $ram      = Get-RamUsage
            $tempInfo = Get-CpuTemperature
            $temp     = $tempInfo.value

            $netResult     = Get-NetworkThroughput -PrevBytes $prevNetBytes -PrevTs $prevTimestamp
            $prevNetBytes  = $netResult.bytes
            $prevTimestamp = $netResult.ts
            $throughput    = $netResult.mbps

            $latency = Get-NetworkLatency

            $payload = [ordered]@{
                cpu_usage          = $cpu
                ram_usage          = $ram
                temperature        = $temp
                temperature_source = $tempInfo.source
                network_throughput = $throughput
                latency_ms         = $latency
                optical_signal_dbm = -18.5   # Valor fijo (no hay sensor óptico real)
                host               = $env:COMPUTERNAME
                timestamp          = [DateTime]::UtcNow.ToString("yyyy-MM-ddTHH:mm:ssZ")
            }

            $json   = $payload | ConvertTo-Json -Compress
            $buffer = [System.Text.Encoding]::UTF8.GetBytes($json)

            $response.StatusCode      = 200
            $response.ContentLength64 = $buffer.Length
            $response.OutputStream.Write($buffer, 0, $buffer.Length)

            # OJO: ${temp} con llaves, si no PowerShell se come el texto pegado a la variable
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] CPU:${cpu}% RAM:${ram}% Temp:${temp}C ($($tempInfo.source)) Net:${throughput} Mbps Lat:${latency}ms"

        } elseif ($request.Url.AbsolutePath -eq "/health") {
            $buffer = [System.Text.Encoding]::UTF8.GetBytes('{"status":"ok"}')
            $response.StatusCode      = 200
            $response.ContentLength64 = $buffer.Length
            $response.OutputStream.Write($buffer, 0, $buffer.Length)

        } else {
            $buffer = [System.Text.Encoding]::UTF8.GetBytes('{"error":"not found"}')
            $response.StatusCode      = 404
            $response.ContentLength64 = $buffer.Length
            $response.OutputStream.Write($buffer, 0, $buffer.Length)
        }

        $response.OutputStream.Close()

    } catch [System.Net.HttpListenerException] {
        break
    } catch {
        Write-Host "Error procesando request: $_"
        try { $context.Response.OutputStream.Close() } catch {}
    }
}

$listener.Stop()
Write-Host "Agente detenido."