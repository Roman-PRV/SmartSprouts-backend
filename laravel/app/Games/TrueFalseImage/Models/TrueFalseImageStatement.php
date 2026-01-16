<?php

namespace App\Games\TrueFalseImage\Models;

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
class TrueFalseImageStatement extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'true_false_image_statements';

    protected $fillable = [
        'level_id',
        'statement',
        'is_true',
        'explanation',
    ];

    protected $casts = [
        'is_true' => 'boolean',
    ];

    /** @var array<int, string> */
    public $translatable = ['statement', 'explanation'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(TrueFalseImageLevel::class, 'level_id');
    }
}
