<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\TripStatus;
use App\Models\Concerns\BelongsToDepot;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single run of a route on a specific date.
 */
class Trip extends Model
{
    use BelongsToDepot, HasFactory;

    protected $fillable = [
        'depot_id', 'schedule_id', 'bus_route_id', 'bus_id', 'driver_id', 'trip_date',
        'scheduled_departure', 'scheduled_arrival', 'actual_departure', 'actual_arrival',
        'status', 'delay_minutes', 'odometer_start', 'odometer_end', 'passenger_count', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            // Immutable so that date arithmetic never changes the stored times.
            'trip_date' => DateOnly::class,
            'scheduled_departure' => 'immutable_datetime',
            'scheduled_arrival' => 'immutable_datetime',
            'actual_departure' => 'immutable_datetime',
            'actual_arrival' => 'immutable_datetime',
            'status' => TripStatus::class,
            'delay_minutes' => 'integer',
            'odometer_start' => 'integer',
            'odometer_end' => 'integer',
            'passenger_count' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
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

    public function adjustments(): HasMany
    {
        return $this->hasMany(TripAdjustment::class)->latest('id');
    }

    public function scopeOn(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('trip_date', $date);
    }

    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('trip_date', [$from->toDateString(), $to->toDateString()]);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', TripStatus::open());
    }

    public function hasDeparted(): bool
    {
        return $this->actual_departure !== null;
    }

    public function isRunning(): bool
    {
        return $this->hasDeparted() && $this->actual_arrival === null && $this->status->isOpen();
    }

    public function wasOnTime(): bool
    {
        return $this->status === TripStatus::Completed
            && $this->delay_minutes <= config('srmss.on_time_grace_minutes');
    }

    /** Expected departure, taking any reported delay into account. */
    public function expectedDeparture(): CarbonInterface
    {
        return $this->actual_departure ?? $this->scheduled_departure->copy()->addMinutes($this->delay_minutes);
    }

    public function durationMinutes(): int
    {
        $start = $this->actual_departure ?? $this->scheduled_departure;
        $end = $this->actual_arrival ?? $this->scheduled_arrival;

        return max(0, (int) $start->diffInMinutes($end));
    }

    public function distanceDriven(): ?int
    {
        return ($this->odometer_start && $this->odometer_end) ? $this->odometer_end - $this->odometer_start : null;
    }
}
