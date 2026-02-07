<?php

return [
    'translation' => [
        'insufficient_funds' => 'Insufficient funds on the translation provider balance.',
        'failed' => 'An error occurred during text translation.',
        'invalid_json' => 'AI provider returned an invalid JSON response.',
        'missing_locale' => 'AI response is missing translation for locale: :locale.',
        'timeout' => 'Translation request timed out. Please try again.',
        'not_found' => 'Translation not found.',
        'provider_failed' => 'Translation provider failed.',
        'deepl_provider_failed' => 'DeepL translation service is unavailable.',
        'unexpected_exception' => 'Translation provider threw an unexpected exception.',
        'details' => [
            'deepl_quota_exceeded' => 'DeepL quota exceeded',
            'openai_quota_exceeded' => 'OpenAI quota exceeded',
            'empty_choices' => 'Empty choices',
            'json_decode_error' => 'JSON decode error',
            'unexpected_structure' => 'Unexpected response structure - check API Key',
            'sdk_internal_error' => 'Internal SDK Error',
        ],
    ],
    'tts' => [
        'invalid_voice' => 'Invalid voice requested',
        'failed' => 'Text-to-speech synthesis failed',
        'quota_exceeded' => 'TTS provider quota exceeded',
        'elevenlabs_failed' => 'ElevenLabs synthesis failed: :error',
        'elevenlabs_quota_exceeded' => 'ElevenLabs quota exceeded: :error',
        'elevenlabs_empty_response' => 'ElevenLabs returned an empty audio response.',
    ],
    'config' => [
        'required_missing' => 'Configuration value for key [:key] is required and must be a non-empty string.',
    ],
];
