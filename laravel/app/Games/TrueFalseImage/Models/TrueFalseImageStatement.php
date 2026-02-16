<?php

namespace App\Games\TrueFalseImage\Models;

use App\Contracts\TtsAudioInterface;
use App\Traits\HasTtsAudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property int $level_id
 * @property string $statement
 * @property bool $is_true
 * @property string|null $explanation
 */
class TrueFalseImageStatement extends Model implements TtsAudioInterface
{
    use HasFactory;
    use HasTranslations;
    use HasTtsAudio;

    protected $table = 'true_false_image_statements';

    protected $fillable = [
        'level_id',
        'statement',
        'is_true',
        'explanation',
        'statement_audio_url',
        'explanation_audio_url',
    ];

    protected $casts = [
        'is_true' => 'boolean',
    ];

    /** @var array<int, string> */
    public $translatable = ['statement', 'explanation', 'statement_audio_url', 'explanation_audio_url'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(TrueFalseImageLevel::class, 'level_id');
    }
}
