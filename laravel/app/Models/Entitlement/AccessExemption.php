<?php

namespace App\Models\Entitlement;

use App\Enums\Entitlement\ExemptionReasonEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unlimited access granted without payment — staff and testers.
 *
 * A row rather than a fifth tier: the Terms describe four purchasable plans, a
 * revoked exemption has to fall back to the tier underneath it, and revenue
 * reporting has to tell a paying Unlimited subscriber from a free grant. None
 * of that survives collapsing this into the tier enum.
 *
 * Mutually exclusive with a paid subscription in both directions, enforced in
 * the service layer.
 *
 * @property int $id
 * @property int $user_id
 * @property ExemptionReasonEnum $reason
 * @property string|null $note
 * @property int|null $granted_by
 * @property \Illuminate\Support\Carbon $granted_at
 */
class AccessExemption extends Model
{
    use HasFactory;

    /**
     * granted_at is the insert time, and a grant is not edited afterwards — it
     * is revoked by deleting the row.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'reason',
        'note',
        'granted_by',
        'granted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reason' => ExemptionReasonEnum::class,
        'granted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The admin who granted it, null once that admin's own account is deleted.
     * The migration says why the exemption outlives them.
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
