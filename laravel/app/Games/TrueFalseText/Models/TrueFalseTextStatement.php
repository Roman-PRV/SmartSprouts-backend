<?php

namespace App\Games\TrueFalseText\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $level_id
 * @property string $statement
 * @property bool $is_true
 * @property string|null $explanation
 */
class TrueFalseTextStatement extends Model
{
    use HasFactory;

    protected $table = 'true_false_text_statements';

    protected $fillable = [
        'level_id',
        'statement',
        'is_true',
        'explanation',
    ];

    protected $casts = [
        'is_true' => 'boolean',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(TrueFalseTextLevel::class, 'level_id');
    }
}
