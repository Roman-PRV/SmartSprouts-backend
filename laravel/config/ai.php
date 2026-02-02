<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI Service Settings
    |--------------------------------------------------------------------------
    |
    | Here you can configure models, prompts, and other parameters for OpenAI.
    |
    */

    'openai' => [
        'translation' => [
            'model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
            'system_prompt' => 'You are a professional multi-language translator.
Translate the provided text into the following languages: :locales.
Return the result STRICTLY as a JSON object where keys are language codes and values are translated strings.
Example: {"en": "Hello", "uk": "Привіт", "es": "Hola"}',
            'request_timeout' => (int) env('OPENAI_REQUEST_TIMEOUT', 30),
            'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('OPENAI_RETRY_TIMES', 3),
            'retry_sleep' => (int) env('OPENAI_RETRY_SLEEP', 1000),
        ],

        'audio' => [
            'tts_model' => env('OPENAI_TTS_MODEL', 'tts-1'),
            'voice' => env('OPENAI_TTS_VOICE', 'alloy'),
        ],
    ],

    'deepl' => [
        'translation' => [
            'request_timeout' => (int) env('DEEPL_REQUEST_TIMEOUT', 30),
            'connect_timeout' => (int) env('DEEPL_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('DEEPL_RETRY_TIMES', 3),
            'retry_sleep' => (int) env('DEEPL_RETRY_SLEEP', 1000),
            'locale_map' => [
                'en' => 'en-US',
            ],
        ],
    ],

    'translation' => [
        'cache' => [
            'enabled' => env('TRANSLATION_CACHE_ENABLED', true),
            'ttl' => (int) env('TRANSLATION_CACHE_TTL', 86400 * 30), // Default 30 days
            'prefix' => env('TRANSLATION_CACHE_PREFIX', 'translation'),
        ],
    ],
];
