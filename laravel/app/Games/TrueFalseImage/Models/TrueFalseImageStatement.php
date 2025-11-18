<?php

namespace App\Games\TrueFalseImage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrueFalseImageStatement extends Model
{
    use HasFactory;

    protected $table = 'true_false_image_statements';

    protected $fillable = [
        'level_id',
        'statement',
        'is_true',
        'explanation',
    ];

    public function trueFalseImage(): BelongsTo
    {
        return $this->belongsTo(TrueFalseImage::class, 'level_id');
    }
}
