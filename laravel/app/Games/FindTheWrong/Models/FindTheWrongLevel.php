<?php

namespace App\Games\FindTheWrong\Models;

use App\Contracts\TranslatableLevelInterface;
use App\Contracts\TtsAudioInterface;
use App\Models\Level;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
     */
    public function storageDirectory(): string
    {
        return self::STORAGE_ROOT.'/'.$this->id;
    }
}
