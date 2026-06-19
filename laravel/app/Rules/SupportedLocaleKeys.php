<?php

namespace App\Rules;

use App\Helpers\ConfigHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ensure a translatable field's array keys are restricted to the locales
 * declared in `config('app.supported_locales')`. Prevents typo'd locale keys
 * (e.g. `eng` instead of `en`) from silently landing in JSON columns and
 * later confusing the TTS observer or admin UI.
 *
 * Bound to config so adding a new locale is a one-line change in app.php,
 * not a hunt across form requests.
 */
class SupportedLocaleKeys implements ValidationRule
{
    /**
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $allowed = ConfigHelper::getStringList('app.supported_locales', []);
        $extra = array_diff(array_map('strval', array_keys($value)), $allowed);

        if ($extra !== []) {
            $fail(__('validation.unsupported_locale_keys', [
                'attribute' => $attribute,
                'keys' => implode(', ', $extra),
                'allowed' => implode(', ', $allowed),
            ]));
        }
    }
}
