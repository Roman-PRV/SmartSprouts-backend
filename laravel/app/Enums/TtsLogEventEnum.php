<?php

namespace App\Enums;

/**
 * Structured logging event types for Text-to-Speech (TTS) services.
 *
 * These event types enable:
 * - Precise log filtering by event category (lifecycle vs. failure)
 * - Monitoring of provider usage and quotas
 * - Easier debugging of the synthesis pipeline
 */
enum TtsLogEventEnum: string
{
    /**
     * Synthesis lifecycle events
     *
     * Tracking the progression of a TTS request.
     */

    /** TTS synthesis process has started */
    case SYNTHESIS_STARTED = 'tts.synthesis_started';

    /** TTS synthesis completed successfully */
    case SYNTHESIS_SUCCESS = 'tts.synthesis_success';

    /**
     * Failure and error events
     */

    /** Synthesis attempt failed (e.g., provider error or network issues) */
    case SYNTHESIS_FAILED = 'tts.synthesis_failed';

    /** Failed to retrieve the list of available voices from the provider */
    case VOICES_FETCH_FAILED = 'tts.voices_fetch_failed';

    /**
     * Provider-level events (critical)
     */

    /** Provider quota/billing limit exceeded */
    case PROVIDER_QUOTA_EXCEEDED = 'tts.provider_quota_exceeded';
}
