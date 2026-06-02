<?php

namespace App\Games\FindTheWrong\Models;

use App\Contracts\TranslatableLevelInterface;
use App\Contracts\TtsAudioInterface;
use App\Models\Level;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Spatie\Translatable\HasTranslations;

class FindTheWrongLevel extends Level implements TranslatableLevelInterface, TtsAudioInterface
{
    use HasTranslations;
    use HasTtsAudio;

    public const STORAGE_ROOT = 'games/find-the-wrong/levels';

    protected $table = 'find_the_wrong_levels';

    protected $fillable = [
        'title',
        'title_audio_url',
        'image_url',
    ];

    protected $attributes = [
        'title' => '{}',
        'title_audio_url' => '{}',
    ];

    /** @var array<int, string> */
    public $translatable = ['title', 'title_audio_url'];

    /**
     * @return HasMany<FindTheWrongItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FindTheWrongItem::class, 'level_id')->orderBy('id');
    }

    /**
     * Directory under the configured upload disk where all of this level's
     * content lives (cover image, TTS audio for title, item assets, etc.).
     *
     * Refuses to build a path for an unpersisted model: without an id (null
     * or any other falsy value set manually) the result would be
     * `games/find-the-wrong/levels/` or `.../levels/0`, and passing the
     * former to Storage::deleteDirectory would wipe the entire levels tree.
     * Checking `$exists` (Eloquent's persisted-state flag, true only after
     * a successful save/find) covers all falsy-id cases at once.
     *
     * @throws LogicException
     */
    public function storageDirectory(): string
    {
        if (! $this->exists) {
            throw new LogicException(
                'FindTheWrongLevel::storageDirectory() requires a persisted model.'
            );
        }

        return self::STORAGE_ROOT.'/'.$this->id;
    }
}
