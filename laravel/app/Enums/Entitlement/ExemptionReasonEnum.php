<?php

namespace App\Enums\Entitlement;

/**
 * Why an account holds unlimited access without paying for it.
 *
 * Both reasons grant exactly the same thing, and nothing branches on which one
 * it is. They are kept apart so a listing of unlimited accounts can say who is
 * staff and who is a tester — a free grant nobody remembers the origin of is
 * the failure this table exists to prevent.
 *
 * Distinct from the Unlimited tier despite identical visible behaviour: that
 * one is a paid product, these are not, and any report that mixes them makes
 * revenue wrong.
 */
enum ExemptionReasonEnum: string
{
    case STAFF = 'staff';

    case TESTER = 'tester';
}
