<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'failed_message' => 'Помилка валідації',
    'active_url' => 'Поле :attribute не є дійсною URL-адресою.',
    'array' => 'Поле :attribute має бути масивом.',
    'ascii' => 'Поле :attribute має містити лише однобайтові алфавітно-цифрові символи та символи.',
    'before' => 'Поле :attribute має бути датою до :date.',
    'before_or_equal' => 'Поле :attribute має бути датою до або дорівнювати :date.',
    'between' => [
        'array' => 'Поле :attribute має містити від :min до :max елементів.',
        'file' => 'Файл :attribute має важити від :min до :max кілобайт.',
        'numeric' => 'Поле :attribute має бути між :min та :max.',
        'string' => 'Поле :attribute має містити від :min до :max символів.',
    ],
    'boolean' => 'Поле :attribute має бути true або false.',
    'confirmed' => 'Поле підтвердження :attribute не збігається.',
    'current_password' => 'Пароль неправильний.',
    'date' => 'Поле :attribute не є дійсною датою.',
    'date_equals' => 'Поле :attribute має бути датою, що дорівнює :date.',
    'date_format' => 'Поле :attribute не відповідає формату :format.',
    'decimal' => 'Поле :attribute має містити :decimal десяткових знаків.',
    'declined' => 'Поле :attribute має бути відхилене.',
    'declined_if' => 'Поле :attribute має бути відхилено, якщо :other дорівнює :value.',
    'different' => 'Поле :attribute та :other мають бути різними.',
    'digits' => 'Поле :attribute має містити :digits цифр.',
    'digits_between' => 'Поле :attribute має містити від :min до :max цифр.',
    'dimensions' => 'Поле :attribute має неприпустимі розміри зображення.',
    'distinct' => 'Поле :attribute має дубльоване значення.',
    'doesnt_end_with' => 'Поле :attribute не повинно закінчуватися одним з наступного: :values.',
    'doesnt_start_with' => 'Поле :attribute не повинно починатися з одного з наступного: :values.',
    'email' => 'Поле :attribute має бути дійсною електронною адресою.',
    'required' => 'Поле :attribute обов\'язкове.',
    'string' => 'Поле :attribute має бути рядком.',
    'timezone' => 'Поле :attribute має бути дійсною часовою зоною.',
    'unique' => 'Таке значення поля :attribute вже існує.',
    'uploaded' => 'Не вдалося завантажити :attribute.',
    'uppercase' => 'Поле :attribute має бути у верхньому регістрі.',
    'url' => 'Поле :attribute має бути дійсним URL.',
    'ulid' => 'Поле :attribute має бути дійсним ULID.',
    'uuid' => 'Поле :attribute має бути дійсним UUID.',
    'min' => [
        'numeric' => 'Поле :attribute має бути не менше :min.',
        'file' => 'Файл :attribute має важити не менше :min кілобайт.',
        'string' => 'Поле :attribute має містити не менше :min символів.',
        'array' => 'Поле :attribute має містити не менше :min елементів.',
    ],
    'integer' => 'Поле :attribute має бути цілим числом.',
    'max' => [
        'numeric' => 'Поле :attribute не може бути більше :max.',
        'file' => 'Файл :attribute не може важити більше :max кілобайт.',
        'string' => 'Поле :attribute не може містити більше :max символів.',
        'array' => 'Поле :attribute не може містити більше :max елементів.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'email' => 'електронна пошта',
        'password' => 'пароль',
    ],

];
