<?php

namespace App\Services\Reports;

use App\Models\FuelLog;
use App\Services\Fleet\FuelEfficiencyCalculator;
use Illuminate\Support\Collection;

class FuelConsumptionReport extends Report
{
    private ?FuelEfficiencyCalculator $calculator = null;

    public static function key(): string
    {
        return 'fuel-consumption';
    }

    public static function title(): string
    {
        return 'Fuel consumption';
    }

    public static function description(): string
    {
        return 'Fuel used and spent per bus, with economy by route and by driver.';
    }

    public static function forManagement(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            'bus' => ['Bus', 'text'],
            'fills' => ['Fills', 'int'],
            'litres' => ['Litres', 'dec'],
            'cost' => ['Cost', 'lkr'],
            'km' => ['Distance (km)', 'int'],
            'km_per_litre' => ['km per litre', 'dec2'],
        ];
    }

    protected function buildRows(): Collection
    {
        return $this->calculator()->byBus($this->period)->map(fn ($row) => [
            'bus' => $row->bus->registration_no,
            'fills' => $row->fills,
            'litres' => $row->litres,
            'cost' => $row->cost,
            'km' => $row->km,
            'km_per_litre' => $row->km_per_litre,
        ]);
    }

    public function summary(): array
    {
        $rows = $this->rows();
        $average = $this->calculator()->fleetAverage($this->period);
        $km = $rows->sum('km');

        return [
            'Fuel used' => number_format($rows->sum('litres'), 1).' L',
            'Fuel cost' => self::format($rows->sum('cost'), 'lkr'),
            'Fleet average' => $average ? number_format($average, 2).' km/L' : '–',
            'Cost per km' => $km > 0 ? self::format($rows->sum('cost') / $km, 'lkr') : '–',
        ];
    }

    public function chart(): ?array
    {
        $byDay = FuelLog::query()
            ->whereBetween('filled_on', $this->period->dateRange())
            ->get(['filled_on', 'litres'])
            ->groupBy(fn (FuelLog $log) => $log->filled_on->toDateString())
            ->map->sum('litres');

        $days = collect($this->period->eachDay())->filter(fn ($d) => $d->lte(today()))->values();

        return [
            'type' => 'bar',
            'unit' => 'L',
            'labels' => $days->map(fn ($d) => $d->format('j M'))->all(),
            'datasets' => [
                ['label' => 'Litres filled', 'data' => $days->map(fn ($d) => round($byDay->get($d->toDateString(), 0), 1))->all(), 'color' => ChartPalette::series(0)],
            ],
        ];
    }

    public function extraTables(): array
    {
        return [
            [
                'title' => 'Fuel by route',
                'columns' => [
                    'route' => ['Route', 'text'],
                    'litres' => ['Litres', 'dec'],
                    'cost' => ['Cost', 'lkr'],
                    'km' => ['Distance (km)', 'int'],
                    'l_per_100km' => ['L/100 km', 'dec'],
                ],
                'rows' => $this->calculator()->byRoute($this->period)->map(fn ($r) => [
                    'route' => $r->route->label(),
                    'litres' => $r->litres,
                    'cost' => $r->cost,
                    'km' => $r->km,
                    'l_per_100km' => $r->l_per_100km,
                ]),
            ],
            [
                'title' => 'Driver fuel economy',
                'columns' => [
                    'driver' => ['Driver', 'text'],
                    'litres' => ['Litres', 'dec'],
                    'km' => ['Distance (km)', 'int'],
                    'km_per_litre' => ['km per litre', 'dec2'],
                    'index' => ['vs. same bus class', 'pct'],
                    'flag' => ['Review', 'text'],
                ],
                'rows' => $this->calculator()->byDriver($this->period)->map(fn ($r) => [
                    'driver' => $r->driver->full_name,
                    'litres' => $r->litres,
                    'km' => $r->km,
                    'km_per_litre' => $r->km_per_litre,
                    'index' => $r->index,
                    'flag' => $r->flagged ? 'Coaching suggested' : '',
                ]),
            ],
        ];
    }

    public function insights(): array
    {
        $insights = [];
        $routes = $this->calculator()->byRoute($this->period)->whereNotNull('l_per_100km');

        if ($top = $routes->first()) {
            $insights[] = "Route {$top->route->route_no} is the highest-usage route at {$top->l_per_100km} L per 100 km.";
        }

        // Compare each bus with others of the same class; coaches always use more fuel than city buses.
        $buses = $this->calculator()->byBus($this->period)->whereNotNull('km_per_litre');
        $classAverage = $buses->groupBy(fn ($r) => $r->bus->service_type->value)
            ->map(fn ($group) => $group->sum('km') / $group->sum(fn ($r) => $r->km / $r->km_per_litre));
        $worst = $buses->sortBy(fn ($r) => $r->km_per_litre / $classAverage[$r->bus->service_type->value])->first();

        if ($worst) {
            $average = $classAverage[$worst->bus->service_type->value];
            $insights[] = sprintf(
                'Bus %s has the lowest economy for a %s bus (%.2f km/L against a class average of %.2f). Check tyre pressure, injectors and idling.',
                $worst->bus->registration_no,
                mb_strtolower($worst->bus->service_type->label()),
                $worst->km_per_litre,
                $average,
            );
        }

        $flagged = $this->calculator()->byDriver($this->period)->where('flagged', true);
        if ($flagged->isNotEmpty()) {
            $insights[] = $flagged->count().' driver(s) achieve less than '.round(config('srmss.fuel_efficiency_alert_ratio') * 100)
                .'% of the usual economy for their class of bus and may benefit from eco-driving coaching: '
                .$flagged->map(fn ($r) => $r->driver->full_name)->implode(', ').'.';
        }

        return $insights;
    }

    private function calculator(): FuelEfficiencyCalculator
    {
        return $this->calculator ??= app(FuelEfficiencyCalculator::class);
    }
}
