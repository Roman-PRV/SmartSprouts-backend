<?php

namespace App\Games\TrueFalseText\Models;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrueFalseTextLevel extends Level
{
    use HasFactory;

    protected $table = 'true_false_text_levels';

    protected $fillable = [
        'title',
        'image_url',
    ];

    public function statements(): HasMany
    {
        return $this->hasMany(TrueFalseTextStatement::class, 'level_id');
    }
}
