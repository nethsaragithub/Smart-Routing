<?php

namespace Database\Seeders;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\BusStatus;
use App\Enums\DriverStatus;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\Recurrence;
use App\Enums\ScheduleStatus;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Depot;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\TripAdjustment;
use App\Models\User;
use App\Services\Routes\RoutePlanner;
use App\Services\Scheduling\ScheduleConflictDetector;
use App\Services\Scheduling\ScheduleProposal;
use App\Services\Trips\TripGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Builds a realistic demo data set: two depots, their routes, fleet and
 * drivers, clash-free timetables, and 45 days of simulated operations
 * (trips, delays, cancellations, fuel fills and maintenance).
 *
 * The simulation is seeded, so every run produces the same story relative
 * to the day it is run.
 */
class DemoDataSeeder extends Seeder
{
    private const HISTORY_DAYS = 45;

    private const FUTURE_DAYS = 7;

    /** Maximum rostered minutes per driver per day when allocating. */
    private const DRIVER_DAY_LIMIT = 450;

    private CarbonImmutable $now;

    /** @var array<int, int> bus id => simulated odometer */
    private array $odometer = [];

    /** @var array<int, int> bus id => km since last fill */
    private array $sinceFill = [];

    /** @var array<int, float> bus id => litres burnt since last fill */
    private array $burnt = [];

    /** @var array<int, array{driver:int, route:int}> */
    private array $lastUse = [];

    /** @var array<int, float> bus id => km per litre */
    private array $economy = [];

    /** @var array<int, float> driver id => economy multiplier */
    private array $driverStyle = [];

    /** @var array<int, int> bus id => odometer at last service */
    private array $lastService = [];

    public function __construct(
        private readonly RoutePlanner $planner,
        private readonly TripGenerator $generator,
        private readonly ScheduleConflictDetector $detector,
    ) {
    }

    public function run(): void
    {
        mt_srand(2026);
        $this->now = CarbonImmutable::now()->second(0);
        $data = require __DIR__.'/data/srmss.php';

        $depots = [];
        foreach ($data as $code => $depotData) {
            $depots[$code] = Depot::create($depotData['depot']);
        }

        $users = $this->users($depots);

        foreach ($data as $code => $depotData) {
            $depot = $depots[$code];
            $buses = $this->buses($depot, $depotData['buses']);
            $drivers = $this->drivers($depot, $depotData['drivers']);
            $routes = $this->routes($depot, $depotData['routes']);
            $this->schedules($depot, $depotData['routes'], $routes, $buses, $drivers, $users['supervisor']);
        }

        $this->assertSchedulesAreClashFree();

        $this->generator->generateBetween($this->now->subDays(self::HISTORY_DAYS), $this->now->addDays(self::FUTURE_DAYS));
        $this->simulateOperations($users['staff']);
        $this->finishingTouches();
    }

    /** @param array<string, Depot> $depots @return array<string, User> */
    private function users(array $depots): array
    {
        $make = fn (string $name, string $email, UserRole $role, ?Depot $depot, string $password, bool $active = true) => User::create([
            'name' => $name, 'email' => $email, 'role' => $role, 'depot_id' => $depot?->id,
            'phone' => '07'.mt_rand(10000000, 99999999), 'is_active' => $active, 'password' => $password,
        ]);

        return [
            'admin' => $make('Dilini Abeysekera', 'admin@srmss.lk', UserRole::Admin, $depots['MHR'], 'Admin@123'),
            'supervisor' => $make('Nimal Jayasinghe', 'supervisor@srmss.lk', UserRole::Supervisor, $depots['MHR'], 'Super@123'),
            'staff' => $make('Kumari Wijesekara', 'staff@srmss.lk', UserRole::Staff, $depots['MHR'], 'Staff@123'),
            'kandy' => $make('Ruwan Ekanayake', 'kandy.supervisor@srmss.lk', UserRole::Supervisor, $depots['KDY'], 'Super@123'),
            'former' => $make('Saman Liyanage', 'former.clerk@srmss.lk', UserRole::Staff, $depots['MHR'], 'Staff@123', false),
        ];
    }

    /** @return Collection<int, Bus> */
    private function buses(Depot $depot, array $rows): Collection
    {
        return collect($rows)->map(function (array $row) use ($depot) {
            [$reg, $fleet, $make, $model, $year, $seats, $service, $km] = $row;
            $interval = $service === 'express' ? 8000 : 10000;
            $start = $km - $this->distanceForHistory($service);

            $bus = Bus::create([
                'depot_id' => $depot->id, 'registration_no' => $reg, 'fleet_no' => $fleet, 'make' => $make,
                'model' => $model, 'year_of_manufacture' => $year, 'seating_capacity' => $seats,
                'service_type' => $service, 'fuel_type' => 'diesel', 'current_mileage' => $start,
                'service_interval_km' => $interval,
                // The oldest bus is off the road for the whole demo period.
                'status' => $reg === 'NA-9921' ? BusStatus::OutOfService : BusStatus::Active,
            ]);

            $this->odometer[$bus->id] = $start;
            $this->lastService[$bus->id] = $start - mt_rand(500, $interval - 1500);
            $this->sinceFill[$bus->id] = 0;
            $this->burnt[$bus->id] = 0.0;
            $this->economy[$bus->id] = match (true) {
                $reg === 'NA-9921' => 2.9,                 // oldest bus, poor economy
                $service === 'express' => mt_rand(310, 345) / 100,
                $service === 'semi_luxury' => mt_rand(330, 370) / 100,
                default => mt_rand(360, 430) / 100,
            };

            return $bus;
        });
    }

    private function distanceForHistory(string $service): int
    {
        return $service === 'express' ? 14000 : 8000;
    }

    /** @return Collection<int, Driver> */
    private function drivers(Depot $depot, array $rows): Collection
    {
        return collect($rows)->map(function (array $row, int $i) use ($depot) {
            [$empNo, $name, $phone, $expiryOffset, $status] = $row;
            $birthYear = mt_rand(1972, 1994);

            $driver = Driver::create([
                'depot_id' => $depot->id, 'employee_no' => $empNo, 'full_name' => $name,
                'nic' => $birthYear.str_pad((string) mt_rand(1, 365), 3, '0', STR_PAD_LEFT).str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT).mt_rand(0, 9),
                'date_of_birth' => CarbonImmutable::create($birthYear, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'phone' => $phone, 'address' => $this->address($depot->code, $i),
                'license_no' => 'B'.mt_rand(1000000, 9999999), 'license_class' => 'D',
                'license_expiry' => $this->now->addDays($expiryOffset)->toDateString(),
                'joined_on' => $this->now->subYears(mt_rand(2, 18))->subDays(mt_rand(0, 300))->toDateString(),
                'status' => $status, 'max_weekly_hours' => 60,
            ]);

            // Most drivers are close to average; two drive less economically.
            $this->driverStyle[$driver->id] = match ($empNo) {
                'MHR-D106' => 0.80,
                'MHR-D109' => 0.87,
                default => mt_rand(96, 105) / 100,
            };

            return $driver;
        });
    }

    private function address(string $depot, int $i): string
    {
        $towns = $depot === 'MHR'
            ? ['Maharagama', 'Pannipitiya', 'Kottawa', 'Homagama', 'Nugegoda', 'Piliyandala', 'Boralesgamuwa', 'Athurugiriya']
            : ['Peradeniya', 'Katugastota', 'Gampola', 'Kundasale', 'Pilimathalawa'];

        return mt_rand(5, 220).'/'.chr(65 + $i % 4).', Temple Road, '.$towns[$i % count($towns)];
    }

    /** @return array<string, BusRoute> keyed by route number */
    private function routes(Depot $depot, array $rows): array
    {
        $routes = [];

        foreach ($rows as $row) {
            [$no, $name, $origin, $destination, $km, $minutes, $service, $minSeats, , , , $stops] = $row;

            if ($origin === null) {
                continue; // extra timetable on an existing route
            }

            $routes[$no] = $this->planner->save(new BusRoute(), [
                'depot_id' => $depot->id, 'route_no' => $no, 'name' => $name, 'origin' => $origin,
                'destination' => $destination, 'distance_km' => $km, 'estimated_duration_minutes' => $minutes,
                'service_type' => $service, 'min_capacity' => $minSeats, 'is_active' => true,
            ], array_map(fn ($s) => ['name' => $s[0], 'lat' => $s[1], 'lng' => $s[2]], $stops));
        }

        return $routes;
    }

    /**
     * Allocate a bus and a driver to every departure using first-fit, with
     * the same turnaround, rest and suitability rules as the live system.
     */
    private function schedules(Depot $depot, array $rows, array $routes, Collection $buses, Collection $drivers, User $creator): void
    {
        $busy = ['bus' => [], 'driver' => []];
        $driverMinutes = [];
        $start = $this->now->subDays(self::HISTORY_DAYS + 5)->toDateString();

        foreach ($rows as $row) {
            [$no, $name, $origin, , , , , , $recurrence, $days, $departures] = $row;
            $route = $routes[$no];

            foreach ($departures as $departure) {
                $dep = Schedule::minutesOfDay($departure);
                $arr = $dep + $route->estimated_duration_minutes;

                // Least-loaded first, so work is shared across the fleet and crews.
                $bus = $buses->sortBy(fn (Bus $b) => array_sum(array_map(fn ($i) => $i[1] - $i[0], $busy['bus'][$b->id] ?? [])))
                    ->first(fn (Bus $b) => $b->status === BusStatus::Active
                    && $b->service_type === $route->service_type
                    && $b->seating_capacity >= $route->min_capacity
                    && $this->isFree($busy['bus'][$b->id] ?? [], $dep, $arr, config('srmss.bus_turnaround_minutes')));

                $driver = $drivers->sortBy(fn (Driver $d) => $driverMinutes[$d->id] ?? 0)
                    ->first(fn (Driver $d) => $d->status === DriverStatus::Active
                    && $d->license_expiry->gt($this->now)
                    && ($driverMinutes[$d->id] ?? 0) + ($arr - $dep) <= self::DRIVER_DAY_LIMIT
                    && $this->isFree($busy['driver'][$d->id] ?? [], $dep, $arr, config('srmss.driver_rest_minutes')));

                if (! $bus || ! $driver) {
                    throw new RuntimeException("Demo data: no free bus/driver for route {$no} at {$departure}.");
                }

                $busy['bus'][$bus->id][] = [$dep, $arr];
                $busy['driver'][$driver->id][] = [$dep, $arr];
                $driverMinutes[$driver->id] = ($driverMinutes[$driver->id] ?? 0) + ($arr - $dep);

                $recurrenceEnum = Recurrence::from($recurrence);

                Schedule::create([
                    'depot_id' => $depot->id, 'bus_route_id' => $route->id, 'bus_id' => $bus->id, 'driver_id' => $driver->id,
                    'departure_time' => $departure, 'arrival_time' => sprintf('%02d:%02d', intdiv($arr, 60), $arr % 60),
                    'recurrence' => $recurrenceEnum,
                    'weekdays' => $recurrenceEnum === Recurrence::Weekly ? $days : null,
                    'month_days' => $recurrenceEnum === Recurrence::Monthly ? $days : null,
                    'start_date' => $start, 'status' => ScheduleStatus::Active,
                    'notes' => $origin === null ? $name : null, 'created_by' => $creator->id,
                ]);
            }
        }
    }

    private function isFree(array $intervals, int $start, int $end, int $buffer): bool
    {
        foreach ($intervals as [$s, $e]) {
            if ($start < $e + $buffer && $s < $end + $buffer) {
                return false;
            }
        }

        return true;
    }

    /** The demo timetable must pass the same conflict rules users face. */
    private function assertSchedulesAreClashFree(): void
    {
        foreach (Schedule::with(['route', 'bus', 'driver'])->get() as $schedule) {
            $report = $this->detector->detect(ScheduleProposal::fromSchedule($schedule));

            if ($report->hasErrors()) {
                throw new RuntimeException('Demo timetable has a clash: '.$report->errors()->first()->message);
            }
        }
    }

    /**
     * Play out every generated trip up to "now": departures, delays,
     * cancellations, arrivals, odometer readings, fuel and servicing.
     */
    private function simulateOperations(User $clerk): void
    {
        $trips = Trip::withoutGlobalScopes()->with(['route', 'bus'])
            ->where('scheduled_departure', '<=', $this->now)
            ->orderBy('scheduled_departure')
            ->get()
            ->groupBy(fn (Trip $t) => $t->trip_date->toDateString());

        // An opening full-tank fill for every bus so economy can be measured.
        foreach (Bus::withoutGlobalScopes()->get() as $bus) {
            $this->fill($bus, $this->now->subDays(self::HISTORY_DAYS + 1)->setTime(19, 0), null, null);
        }

        foreach ($trips as $date => $dayTrips) {
            $day = CarbonImmutable::parse($date);

            foreach ($dayTrips as $trip) {
                $this->playTrip($trip, $clerk, $day->diffInDays($this->now) < 2);
            }

            foreach ($dayTrips->pluck('bus')->unique('id') as $bus) {
                if ($this->sinceFill[$bus->id] >= mt_rand(200, 320)) {
                    $this->fill($bus, $day->setTime(20, mt_rand(0, 50)), $this->lastUse[$bus->id]['driver'] ?? null, $this->lastUse[$bus->id]['route'] ?? null);
                }
                $this->serviceIfDue($bus, $day);
            }

            $this->maybeBreakdown($dayTrips, $day);
        }

        foreach ($this->odometer as $busId => $km) {
            Bus::withoutGlobalScopes()->whereKey($busId)->update([
                'current_mileage' => $km,
                'last_service_mileage' => $this->lastService[$busId],
            ]);
        }
    }

    private function playTrip(Trip $trip, User $clerk, bool $logActivity): void
    {
        $route = $trip->route;
        $busy = in_array($route->route_no, ['138', '177', '120'], true);   // Colombo traffic
        $roll = mt_rand(1, 1000);

        if ($roll <= 35) {
            $reason = [AdjustmentReason::Breakdown, AdjustmentReason::DriverUnavailable, AdjustmentReason::Weather][mt_rand(0, 2)];
            $trip->forceFill(['status' => TripStatus::Cancelled])->saveQuietly();
            $this->adjust($trip, AdjustmentType::Cancellation, $reason, 'Trip cancelled', $clerk, $trip->scheduled_departure->subMinutes(20));

            return;
        }

        $delay = match (true) {
            $roll <= 35 + ($busy ? 560 : 720) => mt_rand(0, 4),
            $roll <= 35 + ($busy ? 850 : 930) => mt_rand(6, 15),
            default => mt_rand(16, $busy ? 45 : 30),
        };
        $travel = (int) round($route->estimated_duration_minutes * (mt_rand(97, $busy ? 118 : 108) / 100));
        $departed = $trip->scheduled_departure->addMinutes($delay);
        $arrived = $departed->addMinutes($travel);
        $start = $this->odometer[$trip->bus_id];
        $distance = (int) round($route->distance_km * mt_rand(99, 103) / 100);
        $isLate = $delay > config('srmss.on_time_grace_minutes');
        $finished = $arrived->lte($this->now);

        $trip->forceFill([
            'actual_departure' => $departed,
            'actual_arrival' => $finished ? $arrived : null,
            'delay_minutes' => $delay,
            'odometer_start' => $start,
            'odometer_end' => $finished ? $start + $distance : null,
            'passenger_count' => $finished ? $this->passengers($trip) : null,
            'status' => $finished ? TripStatus::Completed : ($isLate ? TripStatus::Delayed : TripStatus::OnTime),
        ])->saveQuietly();

        if ($delay > 15) {
            $this->adjust($trip, AdjustmentType::Delay, $busy ? AdjustmentReason::Traffic : AdjustmentReason::Weather, "Delay of {$delay} min reported", $clerk, $trip->scheduled_departure->subMinutes(5));
        }

        if ($logActivity) {
            $this->adjust($trip, AdjustmentType::Departure, null, 'Departed at '.$departed->format('H:i').($delay ? " ({$delay} min late)" : ''), $clerk, $departed);
            if ($finished) {
                $this->adjust($trip, AdjustmentType::Arrival, null, 'Arrived at '.$arrived->format('H:i'), $clerk, $arrived);
            }
        }

        // Fuel burnt depends on the bus, the driver's style and stop-go city traffic.
        $routeFactor = $busy ? 0.88 : 1.0;
        $this->burnt[$trip->bus_id] += $distance / ($this->economy[$trip->bus_id] * ($this->driverStyle[$trip->driver_id] ?? 1.0) * $routeFactor);

        $this->odometer[$trip->bus_id] = $start + $distance;
        $this->sinceFill[$trip->bus_id] += $distance;
        $this->lastUse[$trip->bus_id] = ['driver' => $trip->driver_id, 'route' => $trip->bus_route_id];
    }

    private function passengers(Trip $trip): int
    {
        $hour = (int) $trip->scheduled_departure->format('G');
        $peak = ($hour >= 6 && $hour <= 9) || ($hour >= 16 && $hour <= 18);
        $seats = $trip->bus->seating_capacity;

        return (int) round($seats * mt_rand($peak ? 95 : 45, $peak ? 150 : 95) / 100);
    }

    private function fill(Bus $bus, CarbonImmutable $at, ?int $driverId, ?int $routeId): void
    {
        $litres = $this->burnt[$bus->id] > 0
            ? round($this->burnt[$bus->id] * mt_rand(98, 102) / 100, 1)
            : (float) mt_rand(60, 120);

        (new FuelLog())->forceFill([
            'depot_id' => $bus->depot_id, 'bus_id' => $bus->id, 'driver_id' => $driverId, 'bus_route_id' => $routeId,
            'filled_on' => $at->toDateString(), 'odometer' => $this->odometer[$bus->id], 'litres' => $litres,
            // Diesel price revision 30 days ago.
            'price_per_litre' => $at->lt($this->now->subDays(30)) ? 299.00 : 283.00,
            'full_tank' => true, 'station' => 'Depot pump', 'created_at' => $at, 'updated_at' => $at,
        ])->save();

        $this->sinceFill[$bus->id] = 0;
        $this->burnt[$bus->id] = 0.0;
    }

    private function serviceIfDue(Bus $bus, CarbonImmutable $day): void
    {
        if ($this->odometer[$bus->id] - $this->lastService[$bus->id] < $bus->service_interval_km) {
            return;
        }

        $this->lastService[$bus->id] = $this->odometer[$bus->id];

        MaintenanceRecord::create([
            'depot_id' => $bus->depot_id, 'bus_id' => $bus->id, 'type' => MaintenanceType::Routine,
            'category' => MaintenanceCategory::GeneralService,
            'title' => number_format($bus->service_interval_km).' km service',
            'description' => 'Engine oil and filters, brake inspection, greasing, tyre rotation.',
            'status' => MaintenanceStatus::Completed, 'scheduled_for' => $day, 'started_on' => $day, 'completed_on' => $day,
            'odometer' => $this->odometer[$bus->id], 'cost' => mt_rand(42000, 68000), 'workshop' => 'Depot workshop',
        ]);
    }

    /** Roughly one breakdown every week across the fleet. */
    private function maybeBreakdown(Collection $dayTrips, CarbonImmutable $day): void
    {
        if (mt_rand(1, 100) > 14 || $day->isToday()) {
            return;
        }

        $bus = $dayTrips->pluck('bus')->unique('id')->random();
        $jobs = [
            [MaintenanceCategory::Brakes, 'Brake pads and drums replaced', 28000, 46000],
            [MaintenanceCategory::Tyres, 'Rear tyre burst – two tyres replaced', 64000, 98000],
            [MaintenanceCategory::Electrical, 'Alternator repaired', 18000, 35000],
            [MaintenanceCategory::Engine, 'Overheating – radiator and water pump', 55000, 140000],
            [MaintenanceCategory::Transmission, 'Clutch plate replaced', 72000, 115000],
            [MaintenanceCategory::Body, 'Door mechanism and side panel repair', 12000, 30000],
        ];
        if ($bus->service_type->value !== 'normal') {
            $jobs[] = [MaintenanceCategory::AirConditioning, 'A/C compressor regassed', 25000, 60000];
        }
        [$category, $title, $min, $max] = $jobs[array_rand($jobs)];
        $days = mt_rand(0, 2);

        MaintenanceRecord::create([
            'depot_id' => $bus->depot_id, 'bus_id' => $bus->id, 'type' => MaintenanceType::Corrective,
            'category' => $category, 'title' => $title, 'status' => MaintenanceStatus::Completed,
            'scheduled_for' => $day, 'started_on' => $day, 'completed_on' => $day->addDays($days)->min($this->now),
            'odometer' => $this->odometer[$bus->id], 'cost' => mt_rand($min, $max),
            'workshop' => mt_rand(0, 1) ? 'Depot workshop' : 'SLTB Regional Workshop, Werahera',
        ]);
    }

    /** Current-state details that make the dashboard tell a story. */
    private function finishingTouches(): void
    {
        $find = fn (string $reg) => Bus::withoutGlobalScopes()->where('registration_no', $reg)->first();

        // A bus broke down this morning and is in the workshop: its trips need reassigning.
        $broken = $find('NC-1184');
        MaintenanceRecord::create([
            'depot_id' => $broken->depot_id, 'bus_id' => $broken->id, 'type' => MaintenanceType::Corrective,
            'category' => MaintenanceCategory::Transmission, 'title' => 'Gearbox noise – clutch slipping',
            'description' => 'Reported by driver after the morning run. Awaiting clutch kit.',
            'status' => MaintenanceStatus::InProgress, 'scheduled_for' => $this->now, 'started_on' => $this->now,
            'odometer' => $broken->current_mileage, 'cost' => 85000, 'workshop' => 'Depot workshop',
        ]);
        $broken->update(['status' => BusStatus::Maintenance]);

        $find('NA-9921')->update(['notes' => 'Awaiting body repair after a minor collision. Insurance claim in progress.']);

        // Upcoming planned work.
        $tyres = $find('ND-0563');
        MaintenanceRecord::create([
            'depot_id' => $tyres->depot_id, 'bus_id' => $tyres->id, 'type' => MaintenanceType::Routine,
            'category' => MaintenanceCategory::Tyres, 'title' => 'Tyre rotation and wheel alignment',
            'status' => MaintenanceStatus::Scheduled, 'scheduled_for' => $this->now->addDays(4),
            'workshop' => 'Depot workshop', 'cost' => 18000,
        ]);

        // Two buses close to / past their service interval.
        foreach (['NE-4475' => 320, 'NB-6732' => -180] as $reg => $kmLeft) {
            $bus = $find($reg);
            $bus->update(['last_service_mileage' => $bus->current_mileage - $bus->service_interval_km + $kmLeft]);
        }

        // A short-notice driver swap today, recorded in the audit log.
        $trip = Trip::withoutGlobalScopes()->whereDate('trip_date', $this->now)
            ->where('status', TripStatus::Scheduled)->whereHas('route', fn ($q) => $q->where('route_no', '120'))->first();
        $spare = Driver::withoutGlobalScopes()->where('employee_no', 'MHR-D114')->first();

        if ($trip && $spare && $trip->driver_id !== $spare->id) {
            $old = Driver::withoutGlobalScopes()->find($trip->driver_id);
            $trip->forceFill(['driver_id' => $spare->id])->saveQuietly();
            TripAdjustment::create([
                'trip_id' => $trip->id, 'user_id' => User::where('email', 'supervisor@srmss.lk')->value('id'),
                'type' => AdjustmentType::DriverChange, 'reason' => AdjustmentReason::DriverUnavailable,
                'details' => "Driver {$old->full_name} replaced by {$spare->full_name}", 'note' => 'Called in sick.',
            ]);
        }
    }

    private function adjust(Trip $trip, AdjustmentType $type, ?AdjustmentReason $reason, string $details, User $by, CarbonImmutable $at): void
    {
        (new TripAdjustment())->forceFill([
            'trip_id' => $trip->id, 'user_id' => $by->id, 'type' => $type, 'reason' => $reason,
            'details' => $details, 'created_at' => $at, 'updated_at' => $at,
        ])->save();
    }
}
