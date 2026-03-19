<?php

return [

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

    'elevenlabs' => [
        'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
        'tts' => [
            'api_key' => env('ELEVENLABS_API_KEY'),
            'model' => env('ELEVENLABS_TTS_MODEL', 'eleven_multilingual_v2'),
            'voice' => env('ELEVENLABS_DEFAULT_VOICE', 'hpp4J3VqNfWAUOO0d1Us'),
            'output_format' => env('ELEVENLABS_TTS_OUTPUT_FORMAT', 'mp3_44100_128'),
            'request_timeout' => (int) env('ELEVENLABS_REQUEST_TIMEOUT', 30),
            'connect_timeout' => (int) env('ELEVENLABS_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('ELEVENLABS_RETRY_TIMES', 3),
            'retry_sleep' => (int) env('ELEVENLABS_RETRY_SLEEP', 1000),
        ],
    ],

    'ukrainian_tts' => [
        'base_url' => env('UKRAINIAN_TTS_BASE_URL', 'http://ukrainian-tts:5001'),
        'tts' => [
            'speaker' => env('UKRAINIAN_TTS_SPEAKER', 'lada'),
            'request_timeout' => (int) env('UKRAINIAN_TTS_REQUEST_TIMEOUT', 60),
            'connect_timeout' => (int) env('UKRAINIAN_TTS_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('UKRAINIAN_TTS_RETRY_TIMES', 3),
            'retry_sleep' => (int) env('UKRAINIAN_TTS_RETRY_SLEEP', 2000),
        ],
    ],

    'kokoro' => [
        'base_url' => env('KOKORO_TTS_BASE_URL', 'http://kokoro-tts:8880'),
        'tts' => [
            'default_voice' => env('KOKORO_TTS_DEFAULT_VOICE', 'af_heart'),
            'locale_voices' => [
                'en' => env('KOKORO_TTS_VOICE_EN', 'af_heart'),
                'es' => env('KOKORO_TTS_VOICE_ES', 'ef_dora'),
            ],
            'speed' => (float) env('KOKORO_TTS_SPEED', 1.0),
            'request_timeout' => (int) env('KOKORO_TTS_REQUEST_TIMEOUT', 60),
            'connect_timeout' => (int) env('KOKORO_TTS_CONNECT_TIMEOUT', 10),
            'retry_times' => (int) env('KOKORO_TTS_RETRY_TIMES', 3),
            'retry_sleep' => (int) env('KOKORO_TTS_RETRY_SLEEP', 2000),
        ],
    ],

    'translation' => [

        'cache' => [
            'enabled' => env('TRANSLATION_CACHE_ENABLED', true),
            'ttl' => (int) env('TRANSLATION_CACHE_TTL', 86400 * 30), // Default 30 days
            'prefix' => env('TRANSLATION_CACHE_PREFIX', 'translation'),
        ],
    ],

    'tts' => [
        'provider' => env('TTS_PROVIDER', 'elevenlabs'),

        'locale_dispatch' => [
            'fallback' => env('TTS_LOCALE_DISPATCH_FALLBACK', 'elevenlabs'),
            'locales' => [
                'uk' => env('TTS_PROVIDER_UK', 'ukrainian_tts'),
                'en' => env('TTS_PROVIDER_EN', 'kokoro'),
                'es' => env('TTS_PROVIDER_ES', 'kokoro'),
            ],
        ],

        'auto_generate' => [
            'enabled' => env('TTS_AUTO_GENERATE_ENABLED', true),
            'queue' => env('TTS_QUEUE', 'tts'),
        ],
        'storage' => [
            'enabled' => env('TTS_STORAGE_ENABLED', true),
            'disk' => env('TTS_STORAGE_DISK', 'public'),
            'path_prefix' => env('TTS_STORAGE_PATH_PREFIX', 'games'),
        ],
    ],
];
