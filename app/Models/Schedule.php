<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\Recurrence;
use App\Enums\ScheduleStatus;
use App\Models\Concerns\BelongsToDepot;
use App\Support\RecurrenceRule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring timetable entry: route X departs at HH:MM with bus Y and
 * driver Z on the days described by its recurrence rule.
 */
class Schedule extends Model
{
    use BelongsToDepot, HasFactory;

    protected $fillable = [
        'depot_id', 'bus_route_id', 'bus_id', 'driver_id', 'departure_time', 'arrival_time',
        'recurrence', 'weekdays', 'month_days', 'start_date', 'end_date', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'recurrence' => Recurrence::class,
            'status' => ScheduleStatus::class,
            'weekdays' => 'array',
            'month_days' => 'array',
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(BusRoute::class, 'bus_route_id')->withTrashed();
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', ScheduleStatus::Active);
    }

    /** Schedules whose validity period includes the given date. */
    public function scopeRunningOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }

    public function rule(): RecurrenceRule
    {
        return new RecurrenceRule(
            $this->recurrence,
            $this->start_date,
            $this->end_date,
            array_map('intval', $this->weekdays ?? []),
            array_map('intval', $this->month_days ?? []),
        );
    }

    public function occursOn(CarbonInterface $date): bool
    {
        return $this->status === ScheduleStatus::Active && $this->rule()->occursOn($date);
    }

    public function departureLabel(): string
    {
        return substr($this->departure_time, 0, 5);
    }

    public function arrivalLabel(): string
    {
        return substr($this->arrival_time, 0, 5);
    }

    public function durationMinutes(): int
    {
        return self::minutesOfDay($this->arrival_time) - self::minutesOfDay($this->departure_time);
    }

    public static function minutesOfDay(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
