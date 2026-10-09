<?php

namespace App\Services\Reports;

use App\Enums\MaintenanceType;
use App\Models\MaintenanceRecord;
use Illuminate\Support\Collection;

class MaintenanceSummaryReport extends Report
{
    /** @var Collection<int, MaintenanceRecord>|null */
    private ?Collection $jobs = null;

    public static function key(): string
    {
        return 'maintenance';
    }

    public static function title(): string
    {
        return 'Maintenance summary';
    }

    public static function description(): string
    {
        return 'Routine and corrective work per bus, cost and days off the road.';
    }

    public static function forManagement(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            'bus' => ['Bus', 'text'],
            'routine' => ['Routine jobs', 'int'],
            'corrective' => ['Corrective jobs', 'int'],
            'downtime' => ['Days off road', 'int'],
            'cost' => ['Cost', 'lkr'],
        ];
    }

    protected function buildRows(): Collection
    {
        return $this->jobs()->groupBy('bus_id')->map(function (Collection $jobs) {
            return [
                'bus' => $jobs->first()->bus->registration_no,
                'routine' => $jobs->where('type', MaintenanceType::Routine)->count(),
                'corrective' => $jobs->where('type', MaintenanceType::Corrective)->count(),
                'downtime' => $jobs->sum(fn (MaintenanceRecord $j) => $j->downtimeDays() ?? 0),
                'cost' => $jobs->sum('cost'),
            ];
        })->sortByDesc('cost')->values();
    }

    public function summary(): array
    {
        $jobs = $this->jobs();
        $corrective = $jobs->where('type', MaintenanceType::Corrective)->count();

        return [
            'Jobs recorded' => number_format($jobs->count()),
            'Corrective share' => self::format(self::percent($corrective, $jobs->count()), 'pct'),
            'Days off road' => number_format($this->rows()->sum('downtime')),
            'Maintenance cost' => self::format($jobs->sum('cost'), 'lkr'),
        ];
    }

    public function chart(): ?array
    {
        $byCategory = $this->jobs()
            ->groupBy(fn (MaintenanceRecord $j) => $j->category->label())
            ->map->sum('cost')
            ->sortDesc();

        return [
            'type' => 'bar',
            'horizontal' => true,
            'unit' => 'Rs',
            'labels' => $byCategory->keys()->all(),
            'datasets' => [
                ['label' => 'Cost by category', 'data' => $byCategory->values()->map(fn ($v) => round($v))->all(), 'color' => ChartPalette::series(0)],
            ],
        ];
    }

    public function extraTables(): array
    {
        return [[
            'title' => 'Job log',
            'columns' => [
                'date' => ['Date', 'text'],
                'bus' => ['Bus', 'text'],
                'title' => ['Work', 'text'],
                'type' => ['Type', 'text'],
                'status' => ['Status', 'text'],
                'cost' => ['Cost', 'lkr'],
            ],
            'rows' => $this->jobs()->sortBy('scheduled_for')->map(fn (MaintenanceRecord $j) => [
                'date' => $j->scheduled_for->format('j M Y'),
                'bus' => $j->bus->registration_no,
                'title' => $j->title,
                'type' => $j->type->label(),
                'status' => $j->status->label(),
                'cost' => $j->cost,
            ])->values(),
        ]];
    }

    public function insights(): array
    {
        $rows = $this->rows();

        if ($rows->isEmpty()) {
            return ['No maintenance was recorded in this period.'];
        }

        $insights = [];
        $top = $rows->first();
        $insights[] = "{$top['bus']} cost the most to maintain (".self::format($top['cost'], 'lkr').').';

        $breakdowns = $rows->sortByDesc('corrective')->first();
        if ($breakdowns['corrective'] >= 2) {
            $insights[] = "{$breakdowns['bus']} needed {$breakdowns['corrective']} corrective repairs. Consider a full inspection or replacement plan.";
        }

        return $insights;
    }

    /** @return Collection<int, MaintenanceRecord> */
    private function jobs(): Collection
    {
        return $this->jobs ??= MaintenanceRecord::query()
            ->with('bus')
            ->whereBetween('scheduled_for', $this->period->dateRange())
            ->get();
    }
}
