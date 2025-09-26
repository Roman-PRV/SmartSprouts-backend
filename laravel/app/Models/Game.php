<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Game extends Model
{
    use HasFactory;

    public function getIconUrlAttribute(): string
    {
        $raw = $this->attributes['icon_url'] ?? '';

        if (is_string($raw) && $raw !== '') {
            return url(Storage::url($raw));
        }

        return url(Storage::url('icons/default-icon.png'));
    }
}
