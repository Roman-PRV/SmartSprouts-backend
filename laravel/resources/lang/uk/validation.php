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
	'email' => 'Поле :attribute має бути дійсною електронною адресою.',
	'required' => 'Поле :attribute обов\'язкове.',
	'string' => 'Поле :attribute має бути рядком.',
	'min' => [
		'numeric' => 'Поле :attribute має бути не менше :min.',
		'file' => 'Файл :attribute має важити не менше :min кілобайт.',
		'string' => 'Поле :attribute має містити не менше :min символів.',
		'array' => 'Поле :attribute має містити не менше :min елементів.',
	],
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
