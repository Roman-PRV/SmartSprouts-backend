<?php

return [
    'translation' => [
        'insufficient_funds' => 'Недостатньо коштів на балансі провайдера перекладу.',
        'failed' => 'Сталася помилка під час перекладу тексту.',
        'invalid_json' => 'Провайдер ШІ повернув некоректну JSON-відповідь.',
        'missing_locale' => 'У відповіді ШІ відсутній переклад для локалі: :locale.',
        'timeout' => 'Час очікування запиту перекладу вичерпано. Спробуйте ще раз.',
        'not_found' => 'Переклад не знайдено.',
        'provider_failed' => 'Провайдер перекладу недоступний.',
        'deepl_provider_failed' => 'Сервіс перекладу DeepL недоступний.',
        'unexpected_exception' => 'Провайдер перекладу викинув неочікуване виключення.',
        'details' => [
            'deepl_quota_exceeded' => 'Квоту DeepL вичерпано',
            'openai_quota_exceeded' => 'Квоту OpenAI вичерпано',
            'empty_choices' => 'Порожній варіант відповіді',
            'json_decode_error' => 'Помилка декодування JSON',
            'unexpected_structure' => 'Неочікувана структура відповіді - перевірте API ключ',
            'sdk_internal_error' => 'Внутрішня помилка SDK',
        ],
    ],
];
