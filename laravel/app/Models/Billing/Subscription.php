<?php

namespace App\Models\Billing;

use App\Enums\Billing\SubscriptionStatusEnum;
use App\Enums\Entitlement\TierEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The current state of one account's commercial relationship.
 *
 * No row is ever written for a Free account: Free is the absence of a
 * subscription, and tier resolution falls through to it when nothing is found.
 * A row per account would resolve identically today and quietly make access
 * exemptions ungrantable to everyone, since an exemption may not be granted to
 * an account that holds a subscription.
 *
 * Tier resolution reads tier and never pending_tier — reading the queued one
 * would apply a scheduled downgrade early.
 *
 * One row per account, reused across subscribe cycles, so provider_subscription_id
 * is overwritten when someone subscribes again and the previous identifier is
 * not kept anywhere. Money events are therefore resolved through
 * purchases.provider_reference, which is unique and never rewritten; a webhook
 * arriving weeks late about a closed subscription has nothing to match here.
 *
 * Deleting an account cascades this row away along with the identifier the
 * subscription is cancelled by, so cancellation at the provider has to happen
 * before the account is deleted. The other order leaves an erased account
 * still being charged.
 *
 * @property int $id
 * @property int $user_id
 * @property TierEnum $tier
 * @property TierEnum|null $pending_tier
 * @property SubscriptionStatusEnum $status
 * @property \Illuminate\Support\Carbon $current_period_start
 * @property \Illuminate\Support\Carbon $current_period_end
 * @property string|null $provider_subscription_id
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tier',
        'pending_tier',
        'status',
        'current_period_start',
        'current_period_end',
        'provider_subscription_id',
        'cancelled_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tier' => TierEnum::class,
        'pending_tier' => TierEnum::class,
        'status' => SubscriptionStatusEnum::class,
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
