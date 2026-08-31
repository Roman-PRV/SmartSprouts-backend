<?php

return [
    'translation' => [
        'insufficient_funds' => 'Fondos insuficientes en el saldo del proveedor de traducción.',
        'failed' => 'Se produjo un error durante la traducción del texto.',
        'invalid_json' => 'El proveedor de IA devolvió una respuesta JSON inválida.',
        'missing_locale' => 'Falta la traducción para la configuración regional: :locale en la respuesta de IA.',
        'timeout' => 'Se agotó el tiempo de espera de la solicitud de traducción. Por favor, inténtelo de nuevo.',
        'not_found' => 'Traducción no encontrada.',
        'provider_failed' => 'El proveedor de traducción no está disponible.',
        'deepl_provider_failed' => 'El servicio de traducción DeepL no está disponible.',
        'unexpected_exception' => 'El proveedor de traducción lanzó una excepción inesperada.',
        'details' => [
            'deepl_quota_exceeded' => 'Cuota de DeepL excedida',
            'openai_quota_exceeded' => 'Cuota de OpenAI excedida',
            'empty_choices' => 'Opciones vacías',
            'json_decode_error' => 'Error de decodificación JSON',
            'unexpected_structure' => 'Estructura de respuesta inesperada - verifique la clave API',
            'sdk_internal_error' => 'Error interno del SDK',
        ],
    ],
    'tts' => [
        'invalid_voice' => 'Voz solicitada no válida',
        'failed' => 'La síntesis de texto a voz falló',
        'quota_exceeded' => 'Cuota del proveedor de TTS excedida',
        'elevenlabs_failed' => 'La síntesis de ElevenLabs falló: :error',
        'elevenlabs_quota_exceeded' => 'Cuota de ElevenLabs excedida: :error',
        'elevenlabs_empty_response' => 'ElevenLabs devolvió una respuesta de audio vacía.',
        'ukrainian_tts_failed' => 'La síntesis de Ukrainian TTS falló: :error',
        'ukrainian_tts_empty_response' => 'Ukrainian TTS devolvió una respuesta de audio vacía.',
        'kokoro_failed' => 'La síntesis de Kokoro TTS falló: :error',
        'kokoro_empty_response' => 'Kokoro TTS devolvió una respuesta de audio vacía.',
    ],
    'config' => [
        'required_missing' => 'El valor de configuración para la clave [:key] es obligatorio y debe ser una cadena no vacía.',
    ],
    'auth' => [
        'invalid_credentials' => 'Credenciales inválidas',
        'logged_out' => 'Sesión cerrada correctamente',
    ],
    'account_deletion' => [
        'code_not_applicable' => 'Tu cuenta tiene contraseña; confirma la eliminación con ella en lugar de un código.',
        'invalid_code' => 'El código de eliminación no es válido o ha expirado.',
        'code_sent' => 'Se ha enviado un código de confirmación a tu correo.',
    ],
    'entitlement' => [
        'exemption_not_found' => 'Esta cuenta no tiene ninguna exención de acceso.',
        'exemption_blocked_by_subscription' => 'Esta cuenta tiene una suscripción que todavía otorga un plan. Resuelve la suscripción antes de conceder una exención.',
        'daily_limit_reached' => 'Límite diario alcanzado',
        'level_not_opened_today' => 'Abre el nivel antes de enviar la respuesta',
    ],
];
