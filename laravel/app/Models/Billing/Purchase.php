<?php

namespace App\Models\Billing;

use App\Enums\Billing\PurchaseKindEnum;
use App\Enums\Billing\RefundKindEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One historical money event. A subscription is the relationship as it stands;
 * a purchase is a thing that happened. What happened never changes — a refund
 * appends its outcome to the row rather than revising the event.
 *
 * Not an invoice. The formal invoice is raised by the Merchant of Record, and
 * nothing built on this table may present itself as one.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $user_email_hash
 * @property TierEnum $tier
 * @property TierEnum|null $previous_tier
 * @property PurchaseKindEnum $kind
 * @property int $amount_minor
 * @property string $currency
 * @property int $tax_minor
 * @property string $provider_reference
 * @property \Illuminate\Support\Carbon $purchased_at
 * @property \Illuminate\Support\Carbon|null $refunded_at
 * @property RefundKindEnum|null $refund_kind
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Purchase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_email_hash',
        'tier',
        'previous_tier',
        'kind',
        'amount_minor',
        'currency',
        'tax_minor',
        'provider_reference',
        'purchased_at',
        'refunded_at',
        'refund_kind',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tier' => TierEnum::class,
        'previous_tier' => TierEnum::class,
        'kind' => PurchaseKindEnum::class,
        'amount_minor' => 'integer',
        'tax_minor' => 'integer',
        'purchased_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_kind' => RefundKindEnum::class,
    ];

    /**
     * The buyer, null once their account is deleted — a former holder is matched
     * through user_email_hash instead, never through this relation. The
     * migration says why the row outlives them.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
