<?php

namespace App\Games\FindTheWrong\Models;

use App\Contracts\TtsAudioInterface;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
