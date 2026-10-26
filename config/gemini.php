<?php

/**
 * Configuración de Google Gemini API para FiberPulse AI.
 *
 * Usa el modelo gemini-3.5-flash-lite en capa de pago (Pay-as-you-go).
 * Obtiene la API key desde .env (GEMINI_API_KEY).
 */

return [

    'api_key' => env('GEMINI_API_KEY', ''),

    'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    'timeout' => (int) env('GEMINI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Prompt del sistema para el análisis predictivo
    |--------------------------------------------------------------------------
    */
    'system_prompt' => 'Eres un experto en telemetría de redes de fibra óptica e IAoT. ' .
        'Analizas datos de monitoreo de servidores y nodos en tiempo real. ' .
        'Tu objetivo es diagnosticar y predecir fallas basándote en las métricas. ' .
        'Respondes SIEMPRE en español, de forma concisa y técnica. ' .
        'Debes devolver un JSON estructurado con el estado, el diagnóstico predictivo ' .
        'y la acción recomendada para el técnico de red.',

];
