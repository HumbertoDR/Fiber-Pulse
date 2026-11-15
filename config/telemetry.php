<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Intervalo de Recolección de Telemetría
    |--------------------------------------------------------------------------
    |
    | Define cada cuántos segundos se recolectan y envían métricas de
    | telemetría al dashboard. Valores recomendados:
    |
    | - 1 segundo: Modo demostración/taller (alta frecuencia, muchos usuarios)
    | - 5 segundos: Modo producción estable (balance performance/recursos)
    |
    */

    'interval' => (int) env('TELEMETRY_INTERVAL', 5),

    /*
    |--------------------------------------------------------------------------
    | Timeout de Conexión al Agente Host
    |--------------------------------------------------------------------------
    |
    | Tiempo máximo de espera (en segundos) para conectar al agente de
    | Windows que corre en el puerto 9100. Si expira, usa fallback a
    | datos simulados.
    |
    | Nota: El agente de Windows puede tardar 2-3 segundos en responder
    | debido a las consultas WMI/CIM del sistema.
    |
    */

    'agent_timeout' => 3,

];
