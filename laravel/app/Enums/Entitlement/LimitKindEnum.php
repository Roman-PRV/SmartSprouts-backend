<?php

namespace App\Enums\Entitlement;

/**
 * Which of the two daily counters was exhausted. Sent to the client as
 * `details.limit_kind` beside the 403 — renaming a value is a breaking API
 * change, the same contract `ErrorTypeEnum` carries.
 */
enum LimitKindEnum: string
{
    case STARTED = 'started';

    case COMPLETED = 'completed';
}
