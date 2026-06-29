<?php

namespace App\Enums;

/**
 * The learning area a game belongs to. A game may carry several of these at once
 * (e.g. an image true/false game is both logic and reading), so categories are
 * stored as an array on the game, not a single value. Used to filter the games
 * listing via ?category= and surfaced on the games payload.
 */
enum GameCategory: string
{
    case MATH = 'math';
    case READING = 'reading';
    case LOGIC = 'logic';

    /**
     * Flat list of the backing values. Source of truth for the hand-maintained
     * `@OA` enum literals (guarded by SwaggerCategoryEnumTest).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
