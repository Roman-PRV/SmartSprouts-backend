<?php

namespace App\Games\TrueFalseImage\Models;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class TrueFalseImageLevel extends Level
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'true_false_image_levels';

    protected $fillable = [
        'title',
        'image_url',
    ];

    /** @var array<int, string> */
    public $translatable = ['title'];

    public function statements(): HasMany
    {
        return $this->hasMany(TrueFalseImageStatement::class, 'level_id');
    }
}
