<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\DriverStatus;
use App\Enums\LicenseStatus;
use App\Enums\TripStatus;
use App\Models\Concerns\BelongsToDepot;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use BelongsToDepot, HasFactory, SoftDeletes;

    protected $fillable = [
        'depot_id', 'employee_no', 'full_name', 'nic', 'date_of_birth', 'phone', 'address',
        'license_no', 'license_class', 'license_expiry', 'joined_on', 'status',
        'max_weekly_hours', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'license_expiry' => DateOnly::class,
            'joined_on' => 'date',
            'status' => DriverStatus::class,
            'max_weekly_hours' => 'integer',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', DriverStatus::Active);
    }

    public function licenseStatus(?CarbonInterface $on = null): LicenseStatus
    {
        return LicenseStatus::fromExpiry($this->license_expiry, $on);
    }

    public function isLicenseValidOn(CarbonInterface $date): bool
    {
        return $this->license_expiry !== null && $this->license_expiry->gte($date->copy()->startOfDay());
    }

    /** True when the driver can be rostered on the given date. */
    public function isAvailableOn(CarbonInterface $date): bool
    {
        return $this->status === DriverStatus::Active && $this->isLicenseValidOn($date);
    }

    /**
     * Hours actually driven (or rostered, for trips not yet run) between two dates.
     */
    public function hoursBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        $minutes = $this->trips()
            ->whereBetween('trip_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', TripStatus::Cancelled)
            ->get(['scheduled_departure', 'scheduled_arrival', 'actual_departure', 'actual_arrival'])
            ->sum(fn (Trip $trip) => $trip->durationMinutes());

        return round($minutes / 60, 1);
    }

    /** Name without initials, e.g. "K. A. Sunil Perera" -> "Sunil Perera". */
    public function shortName(): string
    {
        $parts = array_filter(explode(' ', $this->full_name), fn ($p) => ! str_ends_with($p, '.'));

        return implode(' ', $parts) ?: $this->full_name;
    }
}
