<?php

namespace App\Exceptions\Entitlement;

use RuntimeException;

/**
 * An exemption was asked for on an account whose subscription still grants a
 * tier (FR-008e). Rendered as a 409 by the Handler, so every caller is bound by
 * the refusal without catching it.
 */
class SubscriptionBlocksExemptionException extends RuntimeException {}
