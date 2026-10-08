<?php

namespace App\Contracts;

use App\Services\Scheduling\Conflict;
use App\Services\Scheduling\ScheduleProposal;

/**
 * One scheduling business rule (Strategy pattern). The
 * ScheduleConflictDetector runs every registered rule against a proposal,
 * so new rules can be added without touching existing code.
 */
interface ConflictRule
{
    /** @return iterable<Conflict> */
    public function check(ScheduleProposal $proposal): iterable;
}
