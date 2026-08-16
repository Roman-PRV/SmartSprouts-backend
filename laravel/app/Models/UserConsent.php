<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only consent audit-trail entry.
 *
 * Rows are never updated or deleted, with one exception: the account-deletion
 * flow (AccountDeletionService) pseudonymizes them instead of removing them —
 * email_hash (a keyed HMAC of the email) replaces the identity link and the
 * IP/user-agent evidence is cleared.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email_hash
 * @property string $type
 * @property string $document_version
 * @property \Illuminate\Support\Carbon $accepted_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class UserConsent extends Model
{
    use HasFactory;

    public const TYPE_TERMS = 'terms';

    public const TYPE_PRIVACY = 'privacy';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'email_hash',
        'type',
        'document_version',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /**
     * The user who gave this consent (null after account deletion).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
