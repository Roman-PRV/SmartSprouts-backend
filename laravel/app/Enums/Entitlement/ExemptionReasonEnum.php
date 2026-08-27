<?php

namespace App\Enums\Entitlement;

/**
 * Why an account holds unlimited access without paying for it.
 *
 * Both reasons grant exactly the same thing and nothing branches on which it
 * is; they are kept apart so the listing can say who is staff and who is a
 * tester. See AccessExemption for why this is a grant and not a tier.
 */
enum ExemptionReasonEnum: string
{
    case STAFF = 'staff';

    case TESTER = 'tester';
}
