<?php

namespace App\Games\FindTheWrong\Models;

use App\Contracts\TtsAudioInterface;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $level_id
 * @property array<int, array{0: float, 1: float}> $polygon
 * @property string $name
 * @property string $name_audio_url
 * @property string $explanation
 * @property string $explanation_audio_url
 */
class FindTheWrongItem extends Model implements TtsAudioInterface
{
    use HasFactory;
    use HasTranslations;
    use HasTtsAudio;

    public const STORAGE_ROOT = 'games/find-the-wrong/items';

    protected $table = 'find_the_wrong_items';

    protected $fillable = [
        'level_id',
        'polygon',
        'name',
        'name_audio_url',
        'explanation',
        'explanation_audio_url',
    ];

    protected $casts = [
        'polygon' => 'array',
    ];

    protected $attributes = [
        'name' => '{}',
        'name_audio_url' => '{}',
        'explanation' => '{}',
        'explanation_audio_url' => '{}',
    ];

    /** @var array<int, string> */
    public $translatable = ['name', 'name_audio_url', 'explanation', 'explanation_audio_url'];

    /**
     * @return BelongsTo<FindTheWrongLevel, FindTheWrongItem>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(FindTheWrongLevel::class, 'level_id');
    }

    /**
     * Directory under the configured upload disk where all of this item's
     * generated content lives (TTS audio for name and explanation).
     *
     * Refuses to build a path for an unpersisted model: without an id (null
     * or any other falsy value set manually) the result would be
     * `games/find-the-wrong/items/` or `.../items/0`, and passing the former
     * to Storage::deleteDirectory would wipe the entire items tree.
     * Checking `$exists` (Eloquent's persisted-state flag, true only after
     * a successful save/find) covers all falsy-id cases at once.
     *
     * @throws LogicException
     */
    public function storageDirectory(): string
    {
        if (! $this->exists) {
            throw new LogicException(
                'FindTheWrongItem::storageDirectory() requires a persisted model.'
            );
        }

        return self::STORAGE_ROOT.'/'.$this->id;
    }
}
