<?php

namespace App\Games\TrueFalseImage\Models;

use App\Contracts\TranslatableLevelInterface;
use App\Contracts\TrueFalseLevelModelInterface;
use App\Contracts\TtsAudioInterface;
use App\Models\Level;
use App\Traits\HasStorageDirectory;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TrueFalseImageLevel extends Level implements TranslatableLevelInterface, TrueFalseLevelModelInterface, TtsAudioInterface
{
    use HasFactory;
    use HasStorageDirectory;
    use HasTranslations;
    use HasTtsAudio;

    public const STORAGE_ROOT = 'games/true_false_image/levels';

    protected $table = 'true_false_image_levels';

    protected $fillable = [
        'title',
        'image_url',
        'title_audio_url',
    ];

    protected $attributes = [
        'title' => '{}',
        'title_audio_url' => '{}',
    ];

    /** @var array<int, string> */
    public $translatable = ['title', 'title_audio_url'];

    public function statements(): HasMany
    {
        return $this->hasMany(TrueFalseImageStatement::class, 'level_id')->orderBy('id');
    }
}
