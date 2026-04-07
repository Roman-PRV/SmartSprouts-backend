<?php

namespace App\Enums;

/**
 * Structured logging event types for translation services.
 *
 * These event types enable:
 * - Precise log filtering by event category
 * - Elimination of duplicate warnings for the same failure
 * - Easy integration with monitoring tools (Sentry, Datadog, etc.)
 * - Clear semantic distinction between locale-level and provider-level errors
 */
enum TranslationLogEventEnum: string
{
    /**
     * Locale-level events (non-critical)
     *
     * These events indicate that a single locale failed during translation,
     * but the system can continue with other locales or fallback to original text.
     */

    /** A single locale translation failed (e.g., network timeout, malformed response) */
    case LOCALE_FAILED = 'translation.locale.failed';

    /** A locale result is missing or invalid after sanitization */
    case LOCALE_MISSING = 'translation.locale.missing';

    /**
     * Provider-level events (critical)
     *
     * These events indicate provider-wide failures that should trigger
     * failover to backup provider or stop all translation attempts.
     */

    /** Provider quota/billing limit exceeded */
    case PROVIDER_QUOTA_EXCEEDED = 'translation.provider.quota_exceeded';

    /** Provider authentication failed */
    case PROVIDER_AUTH_FAILED = 'translation.provider.auth_failed';

    /** Generic provider-level failure */
    case PROVIDER_FAILED = 'translation.provider.failed';

    /** All locales failed for this provider, triggering fallback */
    case PROVIDER_ALL_LOCALES_FAILED = 'translation.provider.all_locales_failed';

    /**
     * Network and infrastructure events
     *
     * These events indicate infrastructure-level issues that may be transient.
     */

    /** Network timeout or connection error */
    case NETWORK_TIMEOUT = 'translation.network.timeout';

    /** SDK internal error (e.g., unexpected response structure) */
    case SDK_ERROR = 'translation.sdk.error';
}
