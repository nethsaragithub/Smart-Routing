<?php

namespace App\Services\Scheduling;

use App\Contracts\ConflictRule;

/**
 * Runs every registered ConflictRule against a proposed schedule.
 * Rules are injected (see AppServiceProvider), so the detector is open for
 * extension but closed for modification.
 */
class ScheduleConflictDetector
{
    /** @var list<ConflictRule> */
    private array $rules;

    /** @param iterable<ConflictRule> $rules */
    public function __construct(iterable $rules)
    {
        $this->rules = is_array($rules) ? array_values($rules) : iterator_to_array($rules, false);
    }

    public function detect(ScheduleProposal $proposal): ConflictReport
    {
        $conflicts = [];

        foreach ($this->rules as $rule) {
            foreach ($rule->check($proposal) as $conflict) {
                $conflicts[] = $conflict;
            }
        }

        return new ConflictReport($conflicts);
    }

    /** @return list<ConflictRule> */
    public function rules(): array
    {
        return $this->rules;
    }
}
