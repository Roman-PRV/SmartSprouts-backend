<?php

namespace App\Enums\Billing;

/**
 * State of a subscription's commercial relationship.
 *
 * A scheduled downgrade is NOT a status: the subscription stays ACTIVE and the
 * queued tier lives in its own column, because a downgrade changes what will be
 * charged next without changing whether the subscription is running.
 *
 *   (none) ──purchase──────────► ACTIVE
 *   ACTIVE ──cancel───────────► CANCELLING ──period end──► ENDED
 *   ACTIVE ──renewal fails────► PAST_DUE ──grace expires──► ENDED
 *   PAST_DUE ──payment recovers► ACTIVE
 *   CANCELLING ──undo cancel──► ACTIVE
 *   ENDED ──new purchase──────► ACTIVE
 */
enum SubscriptionStatusEnum: string
{
    case ACTIVE = 'active';

    /** Cancellation requested; access continues to the end of the paid period. */
    case CANCELLING = 'cancelling';

    /** A renewal payment failed; access continues through the grace period. */
    case PAST_DUE = 'past_due';

    case ENDED = 'ended';

    /**
     * Whether the subscription's tier is the one in force, or the account falls
     * through to Free. Not a question about access — Free grants access too.
     *
     * Cancelling and past-due both still grant it: the account keeps what it paid
     * for until the period ends or the grace window closes.
     *
     * Also decides whether an access exemption may be granted — a subscription
     * still granting a tier blocks one, so nobody pays for an allowance a free
     * grant already overrides.
     *
     * Exhaustive on purpose. `$this !== self::ENDED` would read the same today
     * and hand a paid tier to every status added later — including `paused`,
     * which the provider mapping will bring. A missing arm fails static analysis
     * instead.
     */
    public function grantsTier(): bool
    {
        return match ($this) {
            self::ACTIVE, self::CANCELLING, self::PAST_DUE => true,
            self::ENDED => false,
        };
    }
}
