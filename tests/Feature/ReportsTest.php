<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Reports\TripCompletionReport;
use App\Services\Trips\TripGenerator;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public static function reports(): array
    {
        return [['trip-completion'], ['route-performance'], ['fuel-consumption'], ['maintenance'], ['driver-hours']];
    }

    #[DataProvider('reports')]
    public function test_every_report_renders_for_weekly_and_monthly_periods(string $key): void
    {
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->get("/reports/{$key}?period=weekly")->assertOk();
        $this->actingAs($supervisor)->get("/reports/{$key}?period=monthly")->assertOk();
    }

    #[DataProvider('reports')]
    public function test_every_report_exports_to_pdf_and_csv(string $key): void
    {
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->get("/reports/{$key}/export/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->actingAs($supervisor)->get("/reports/{$key}/export/csv")
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_unknown_report_returns_not_found(): void
    {
        $this->actingAs($this->supervisor())->get('/reports/does-not-exist')->assertNotFound();
    }

    public function test_trip_completion_figures_are_calculated_correctly(): void
    {
        $this->schedule();
        app(TripGenerator::class)->generateBetween(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-08'));

        $trips = Trip::orderBy('trip_date')->get();
        $trips[0]->update(['status' => TripStatus::Completed, 'delay_minutes' => 2]);
        $trips[1]->update(['status' => TripStatus::Completed, 'delay_minutes' => 20]);
        $trips[2]->update(['status' => TripStatus::Cancelled]);
        $trips[3]->update(['status' => TripStatus::Completed, 'delay_minutes' => 0]);

        $report = new TripCompletionReport(ReportPeriod::between(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-08')));
        $summary = $report->summary();

        $this->assertSame('4', $summary['Trips scheduled']);
        $this->assertSame('75%', $summary['Completion rate']);
        $this->assertSame('67%', $summary['On-time rate']);
        $this->assertSame('1', $summary['Cancelled trips']);
    }
}
