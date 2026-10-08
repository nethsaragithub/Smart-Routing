<?php

namespace Tests\Unit;

use App\Enums\Recurrence;
use App\Support\RecurrenceRule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * White-box tests for the recurrence value object that decides on which
 * dates a timetable runs.
 */
class RecurrenceRuleTest extends TestCase
{
    private function date(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_daily_rule_runs_every_day_inside_its_validity_period(): void
    {
        $rule = new RecurrenceRule(Recurrence::Daily, $this->date('2026-10-01'), $this->date('2026-10-31'));

        $this->assertTrue($rule->occursOn($this->date('2026-10-01')));
        $this->assertTrue($rule->occursOn($this->date('2026-10-31')));
        $this->assertFalse($rule->occursOn($this->date('2026-09-30')), 'before start date');
        $this->assertFalse($rule->occursOn($this->date('2026-11-01')), 'after end date');
    }

    public function test_weekly_rule_runs_only_on_selected_weekdays(): void
    {
        // Monday to Friday
        $rule = new RecurrenceRule(Recurrence::Weekly, $this->date('2026-10-01'), null, [1, 2, 3, 4, 5]);

        $this->assertTrue($rule->occursOn($this->date('2026-10-05')));   // Monday
        $this->assertTrue($rule->occursOn($this->date('2026-10-09')));   // Friday
        $this->assertFalse($rule->occursOn($this->date('2026-10-10')));  // Saturday
        $this->assertFalse($rule->occursOn($this->date('2026-10-11')));  // Sunday
        $this->assertSame('Mon, Tue, Wed, Thu, Fri', $rule->describe());
    }

    public function test_monthly_rule_runs_on_selected_days_of_the_month(): void
    {
        $rule = new RecurrenceRule(Recurrence::Monthly, $this->date('2026-01-01'), null, [], [1, 15]);

        $this->assertTrue($rule->occursOn($this->date('2026-10-15')));
        $this->assertFalse($rule->occursOn($this->date('2026-10-16')));
        $this->assertCount(2, $rule->occurrencesBetween($this->date('2026-10-01'), $this->date('2026-10-31')));
    }

    public function test_shared_date_is_found_between_overlapping_rules(): void
    {
        $weekdays = new RecurrenceRule(Recurrence::Weekly, $this->date('2026-10-01'), null, [1, 2, 3, 4, 5]);
        $sundays = new RecurrenceRule(Recurrence::Weekly, $this->date('2026-10-01'), null, [7]);
        $daily = new RecurrenceRule(Recurrence::Daily, $this->date('2026-10-10'));

        $this->assertNull($weekdays->firstSharedDateWith($sundays), 'weekdays and Sundays never meet');
        $this->assertSame('2026-10-11', $sundays->firstSharedDateWith($daily)->toDateString());
        $this->assertSame('2026-10-12', $weekdays->firstSharedDateWith($daily)->toDateString());
    }

    public function test_rules_with_separate_date_ranges_never_share_a_date(): void
    {
        $october = new RecurrenceRule(Recurrence::Daily, $this->date('2026-10-01'), $this->date('2026-10-31'));
        $november = new RecurrenceRule(Recurrence::Daily, $this->date('2026-11-01'), $this->date('2026-11-30'));

        $this->assertNull($october->firstSharedDateWith($november));
    }

    public function test_runs_per_week_is_used_for_working_hour_estimates(): void
    {
        $this->assertSame(7.0, (new RecurrenceRule(Recurrence::Daily, $this->date('2026-10-01')))->runsPerWeek());
        $this->assertSame(3.0, (new RecurrenceRule(Recurrence::Weekly, $this->date('2026-10-01'), null, [1, 3, 5]))->runsPerWeek());
    }
}
