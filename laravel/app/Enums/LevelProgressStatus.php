<?php

namespace App\Enums;

/**
 * A player's progress on a single level, derived from their most recent attempt
 * (not their best). Surfaced on the levels-list response so the client can frame
 * each level: mastered (perfect last attempt), not_perfect (played but the last
 * attempt missed something), not_started (never attempted).
 */
enum LevelProgressStatus: string
{
    /** Last attempt scored full marks (score === total_questions). */
    case MASTERED = 'mastered';

    /** At least one attempt exists, but the last one was not perfect. */
    case NOT_PERFECT = 'not_perfect';

    /** No attempt recorded for this level. */
    case NOT_STARTED = 'not_started';

    /**
     * Map a single attempt's raw score to a played status. A zero-question
     * attempt is never "mastered" (0 === 0 would otherwise read as perfect).
     * The "no attempt" case (NOT_STARTED) is the caller's decision.
     */
    public static function fromScore(int $score, int $total): self
    {
        return match (true) {
            $total > 0 && $score === $total => self::MASTERED,
            default => self::NOT_PERFECT,
        };
    }
}
