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
    ],
];
