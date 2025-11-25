<?php

namespace App\Games\TrueFalseImage\Models;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrueFalseImageLevel extends Level
{
    use HasFactory;

    protected $table = 'true_false_image_levels';

    protected $fillable = [
        'title',
        'image_url',
    ];

    public function statements(): HasMany
    {
        return $this->hasMany(TrueFalseImageStatement::class, 'level_id');
    }
}
