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

    'failed_message' => 'Validation failed', // New custom key
    'email' => 'The :attribute field must be a valid email address.',
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute field must be a string.',
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
        'string' => 'The :attribute field must be at least :min characters.',
        'array' => 'The :attribute field must have at least :min items.',
    ],
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'string' => 'The :attribute field must not be greater than :max characters.',
        'array' => 'The :attribute field must not have more than :max items.',
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

    'attributes' => [],

];
