<?php

/*
|--------------------------------------------------------------------------
| Access tiers and billing
|--------------------------------------------------------------------------
|
| Tiers live here rather than in the database because their values are stated
| in the Terms of Service: they must be reviewable in git beside the document
| that describes them, and must change by deploy rather than from an admin
| screen.
|
| A null limit means NO limit and is never compared against a count. Expressing
| it as a large number instead would make "unlimited" indistinguishable from
| "a lot", and every comparison would keep working while meaning something else.
|
*/

return [

    /*
    | Keys mirror TierEnum, which documents why there are exactly four. Display
    | names are not here — they come from the frontend `billing` i18n namespace,
    | so these keys are identity and must stay stable across renames.
    |
    | completed_limit — distinct levels finished today
    | started_limit   — distinct levels opened today
    */
    'tiers' => [

        'free' => [
            'completed_limit' => 1,
            'started_limit' => 3,
            'price_minor' => 0,
        ],

        'plus' => [
            'completed_limit' => 4,
            'started_limit' => 8,
            'price_minor' => 100,
        ],

        'premium' => [
            'completed_limit' => 20,
            'started_limit' => 40,
            'price_minor' => 500,
        ],

        'unlimited' => [
            'completed_limit' => null,
            'started_limit' => null,
            'price_minor' => 1000,
        ],

    ],

    /*
    | Not env-configurable: the prices above are written in this currency and
    | assume its two decimal places, so 100 is €1.00. Switching to a currency
    | with a different exponent would change what every figure above means
    | without changing any of them — that is a commercial decision taken together
    | with re-pricing, not a deploy-time setting.
    |
    | Net of VAT; the Merchant of Record adds the customer's country rate at
    | checkout.
    */
    'currency' => 'EUR',

    /*
    | Gates checkout initiation only; webhooks stay live so the sandbox exercises
    | the production path. Turning this on is the last step of a checklist that is
    | mostly not engineering work — activated Terms, updated Privacy Policy, a
    | named contracting party, a verified provider account — so it defaults to
    | false everywhere and is never flipped as part of a deploy.
    */
    'purchasing_enabled' => env('BILLING_PURCHASING_ENABLED', false),

    /*
    | The Merchant of Record is the legal seller: card details never reach this
    | application, and the customer's receipt carries its name rather than ours.
    */
    'provider' => env('BILLING_PROVIDER', 'paddle'),

    /*
    | Daily usage rows are operational, not evidential — nothing legal or
    | financial depends on last month's level opens. A short window still answers
    | "why was I blocked yesterday" for support.
    */
    'usage_retention_days' => (int) env('BILLING_USAGE_RETENTION_DAYS', 7),

    /*
    | How long access survives a failed renewal. The provider owns this and drives
    | it by webhook; the value here lets reconciliation find subscriptions whose
    | webhook never arrived, which would otherwise sit past-due and play for free
    | indefinitely with nothing to detect it.
    */
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 14),

    /*
    | Daily level opens above which an unlimited account is listed for human
    | review. A signal, not a cap: nothing is blocked or slowed, because an
    | "unlimited" tier that silently stops working would contradict its own name.
    | A child playing hard reaches tens of levels a day, so tripping this means
    | something non-human is happening.
    */
    'fair_use_review_threshold' => (int) env('BILLING_FAIR_USE_REVIEW_THRESHOLD', 300),

];
