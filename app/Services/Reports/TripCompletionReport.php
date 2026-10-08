<?php

namespace App\Services\Reports;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Support\Collection;

class TripCompletionReport extends Report
{
    public static function key(): string
    {
        return 'trip-completion';
    }

    public static function title(): string
    {
        return 'Trip completion';
    }

    public static function description(): string
    {
        return 'How many scheduled trips ran, how many ran on time and how many were cancelled, day by day.';
    }

    public function columns(): array
    {
        return [
            'date' => ['Date', 'text'],
            'scheduled' => ['Scheduled', 'int'],
            'completed' => ['Completed', 'int'],
            'on_time' => ['On time', 'int'],
            'late' => ['Late', 'int'],
            'cancelled' => ['Cancelled', 'int'],
            'completion' => ['Completion', 'pct'],
            'punctuality' => ['Punctuality', 'pct'],
        ];
    }

    protected function buildRows(): Collection
    {
        $trips = Trip::query()
            ->whereBetween('trip_date', $this->period->dateRange())
            ->get(['trip_date', 'status', 'delay_minutes'])
            ->groupBy(fn (Trip $t) => $t->trip_date->toDateString());

        return collect($this->period->eachDay())
            ->filter(fn ($day) => $day->lte(today()))
            ->map(function ($day) use ($trips) {
                $dayTrips = $trips->get($day->toDateString(), collect());
                $completed = $dayTrips->where('status', TripStatus::Completed);
                $onTime = $completed->filter->wasOnTime()->count();
                $cancelled = $dayTrips->where('status', TripStatus::Cancelled)->count();

                return [
                    'date' => $day->format('D j M'),
                    'scheduled' => $dayTrips->count(),
                    'completed' => $completed->count(),
                    'on_time' => $onTime,
                    'late' => $completed->count() - $onTime,
                    'cancelled' => $cancelled,
                    'completion' => self::percent($completed->count(), $dayTrips->count()),
                    'punctuality' => self::percent($onTime, $completed->count()),
                    'avg_delay' => $completed->avg('delay_minutes'),
                ];
            })
            ->values();
    }

    public function summary(): array
    {
        $rows = $this->rows();
        $scheduled = $rows->sum('scheduled');
        $completed = $rows->sum('completed');

        return [
            'Trips scheduled' => number_format($scheduled),
            'Completion rate' => self::format(self::percent($completed, $scheduled), 'pct'),
            'On-time rate' => self::format(self::percent($rows->sum('on_time'), $completed), 'pct'),
            'Cancelled trips' => number_format($rows->sum('cancelled')),
        ];
    }

    public function chart(): ?array
    {
        $rows = $this->rows();

        return [
            'type' => 'bar',
            'stacked' => true,
            'unit' => 'trips',
            'labels' => $rows->pluck('date')->all(),
            'datasets' => [
                ['label' => 'On time', 'data' => $rows->pluck('on_time')->all(), 'color' => ChartPalette::ON_TIME],
                ['label' => 'Late', 'data' => $rows->pluck('late')->all(), 'color' => ChartPalette::LATE],
                ['label' => 'Cancelled', 'data' => $rows->pluck('cancelled')->all(), 'color' => ChartPalette::CANCELLED],
            ],
        ];
    }

    public function insights(): array
    {
        $rows = $this->rows()->where('scheduled', '>', 0);

        if ($rows->isEmpty()) {
            return ['No trips were scheduled in this period.'];
        }

        $insights = [];
        $worst = $rows->sortBy('completion')->first();
        $insights[] = "Lowest completion was on {$worst['date']} at ".self::format($worst['completion'], 'pct')." ({$worst['cancelled']} cancelled).";

        $previous = new self($this->period->previous());
        $prevRows = $previous->rows();
        $now = self::percent($rows->sum('completed'), $rows->sum('scheduled'));
        $before = self::percent($prevRows->sum('completed'), $prevRows->sum('scheduled'));

        if ($now !== null && $before !== null) {
            $diff = round($now - $before, 1);
            $insights[] = $diff >= 0
                ? "Completion improved by {$diff} points compared with the previous period ({$before}%)."
                : 'Completion fell by '.abs($diff)." points compared with the previous period ({$before}%).";
        }

        $lateShare = self::percent($rows->sum('late'), $rows->sum('completed'));
        if ($lateShare !== null && $lateShare > 15) {
            $insights[] = "{$lateShare}% of completed trips left late. Review departure buffers on the busiest routes.";
        }

        return $insights;
    }
}
