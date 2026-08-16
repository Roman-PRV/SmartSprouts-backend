<?php

/*
|--------------------------------------------------------------------------
| Legal document versions
|--------------------------------------------------------------------------
|
| Single source of truth for the currently effective Terms of Service and
| Privacy Policy versions. Consent capture and the re-consent gate compare
| a user's accepted versions against these values, so bump them ONLY when
| the published documents actually change (all three locales share one
| version identifier).
|
*/

return [

    'terms_version' => '2026-07-12',

    'privacy_version' => '2026-07-12',

    /*
    | Secret key for the HMAC over the email in anonymized consent rows.
    | A keyed hash keeps the pseudonym reproducible (a complainant's address
    | can be matched to their consent evidence) while a leaked database alone
    | can't be dictionary-reversed. Deliberately NOT the app key: rotating
    | APP_KEY must not sever the evidence link.
    */
    'email_hash_key' => env('LEGAL_HASH_KEY'),

];
