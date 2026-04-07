<?php

namespace App\Enums\Tts;

use App\Games\TrueFalseImage\Models\TrueFalseImageLevel;
use App\Games\TrueFalseImage\Models\TrueFalseImageStatement;
use App\Games\TrueFalseText\Models\TrueFalseTextLevel;
use App\Games\TrueFalseText\Models\TrueFalseTextStatement;

enum TtsModelMappingEnum: string
{
    case TRUE_FALSE_TEXT_STATEMENT = TrueFalseTextStatement::class;
    case TRUE_FALSE_IMAGE_STATEMENT = TrueFalseImageStatement::class;
    case TRUE_FALSE_TEXT_LEVEL = TrueFalseTextLevel::class;
    case TRUE_FALSE_IMAGE_LEVEL = TrueFalseImageLevel::class;

    /**
     * Get the game type identifier for storage paths.
     */
    public function getGameType(): string
    {
        return match ($this) {
            self::TRUE_FALSE_TEXT_STATEMENT,
            self::TRUE_FALSE_TEXT_LEVEL => 'true_false_text',
            self::TRUE_FALSE_IMAGE_STATEMENT,
            self::TRUE_FALSE_IMAGE_LEVEL => 'true_false_image',
        };
    }

    /**
     * Get the entity type identifier for storage paths.
     */
    public function getEntityType(): string
    {
        return match ($this) {
            self::TRUE_FALSE_TEXT_STATEMENT,
            self::TRUE_FALSE_IMAGE_STATEMENT => 'statements',
            self::TRUE_FALSE_TEXT_LEVEL,
            self::TRUE_FALSE_IMAGE_LEVEL => 'levels',
        };
    }

    /**
     * Create an enum instance from a model object.
     */
    public static function fromModel(object $model): ?self
    {
        return self::tryFrom(get_class($model));
    }
}
