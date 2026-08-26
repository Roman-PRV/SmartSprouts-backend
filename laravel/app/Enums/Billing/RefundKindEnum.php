<?php

namespace App\Enums\Billing;

/**
 * Who decided a purchase should be refunded.
 *
 * The goodwill allowance is consumable once per purchase kind, and only a
 * goodwill refund consumes it: the Merchant of Record is the legal seller and
 * may refund on its own buyer-protection terms, which is not a favour the
 * account asked for and must not spend the one it is still owed.
 *
 * A null column means the purchase was never refunded — the two cases here both
 * describe a refund that happened.
 */
enum RefundKindEnum: string
{
    case GOODWILL = 'goodwill';

    case PROVIDER = 'provider';
}
