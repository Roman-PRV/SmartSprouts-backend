<?php

namespace App\Games\TrueFalseText\Models;

use App\Contracts\StatementModelInterface;
use App\Traits\HasStorageDirectory;
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
class TrueFalseTextStatement extends Model implements StatementModelInterface
{
    use HasFactory;
    use HasStorageDirectory;
    use HasTranslations;
    use HasTtsAudio;

    public const STORAGE_ROOT = 'games/true_false_text/statements';

    protected $table = 'true_false_text_statements';

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

    protected $attributes = [
        'statement' => '{}',
        'explanation' => '{}',
        'statement_audio_url' => '{}',
        'explanation_audio_url' => '{}',
    ];

    /** @var array<int, string> */
    public $translatable = ['statement', 'explanation', 'statement_audio_url', 'explanation_audio_url'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(TrueFalseTextLevel::class, 'level_id');
    }
}
