<?php

namespace App\Games\TrueFalseText\Models;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TrueFalseTextLevel extends Level
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'true_false_text_levels';

    protected $fillable = [
        'title',
        'image_url',
        'text',
    ];

    /** @var array<int, string> */
    public $translatable = ['title', 'text'];

    public function statements(): HasMany
    {
        return $this->hasMany(TrueFalseTextStatement::class, 'level_id');
    }
}
