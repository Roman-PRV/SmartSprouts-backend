<?php

namespace App\Enums;

/**
 * Machine-readable error identifiers the client branches on, sent as
 * `error_type` beside the message. Only errors the client has to branch on
 * carry one; a 404 or a 422 is handled by its status alone.
 *
 * The values are a contract with the frontend — renaming one is a breaking
 * API change.
 */
enum ErrorTypeEnum: string
{
    case SUBSCRIPTION_STILL_GRANTS_TIER = 'SUBSCRIPTION_STILL_GRANTS_TIER';
}
