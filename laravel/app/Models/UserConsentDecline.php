<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account's refusal of one legal document version.
 *
 * Never cleared on a later acceptance: an acceptance of the same version wins
 * on its own, and after the next version bump these rows stop matching what is
 * in force. Nothing has to go back and tidy them.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $document_version
 * @property \Illuminate\Support\Carbon $declined_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UserConsentDecline extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'document_version',
        'declined_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'declined_at' => 'datetime',
    ];

    /**
     * The user who refused this document version.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
