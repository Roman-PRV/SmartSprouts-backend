<?php

namespace App\Games\FindTheWrong\Models;

use App\Contracts\TtsAudioInterface;
use App\Models\Level;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class FindTheWrongLevel extends Level implements TtsAudioInterface
{
    use HasFactory;
    use HasTranslations;
    use HasTtsAudio;

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

    public function items(): HasMany
    {
        return $this->hasMany(FindTheWrongItem::class, 'level_id')->orderBy('id');
    }
}
