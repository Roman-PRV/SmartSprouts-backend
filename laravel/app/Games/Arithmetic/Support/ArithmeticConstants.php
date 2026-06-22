<?php

namespace App\Games\Arithmetic\Support;

/**
 * Catalog dimensions shared by every arithmetic pair-match game. The content is
 * fully deterministic (no DB tables): each game has LEVEL_COUNT levels, and
 * level N is FACTS_PER_LEVEL facts of the form `N ∘ b` with b in
 * 1..FACTS_PER_LEVEL.
 */
final class ArithmeticConstants
{
    /**
     * Number of levels per arithmetic game.
     */
    public const LEVEL_COUNT = 10;

    /**
     * Number of facts (equation cards) per level.
     *
     * Keep ArithmeticAttemptRequest's OpenAPI `maximum` in sync — swagger-php
     * annotations are static and cannot reference this constant.
     */
    public const FACTS_PER_LEVEL = 10;
}
