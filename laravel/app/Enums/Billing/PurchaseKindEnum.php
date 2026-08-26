<?php

namespace App\Enums\Billing;

/**
 * What a purchase was: a first paid tier, or a move up from one already held.
 *
 * The distinction is not bookkeeping detail — the two carry different refund
 * rules and different revert targets. Refunding an initial purchase drops the
 * account to Free; refunding an upgrade restores the tier recorded in
 * `previous_tier`, which is why that column exists.
 *
 * A downgrade is deliberately absent: it schedules a tier change at the period
 * boundary and moves no money, so it writes no purchase row at all.
 */
enum PurchaseKindEnum: string
{
    case INITIAL = 'initial';

    case UPGRADE = 'upgrade';
}
