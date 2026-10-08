<?php

namespace App\Models;

use App\Enums\BusStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\ServiceType;
use App\Models\Concerns\BelongsToDepot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bus extends Model
{
    use BelongsToDepot, HasFactory, SoftDeletes;

    protected $fillable = [
        'depot_id', 'registration_no', 'fleet_no', 'make', 'model', 'year_of_manufacture',
        'seating_capacity', 'service_type', 'fuel_type', 'current_mileage', 'service_interval_km',
        'last_service_mileage', 'last_service_date', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'status' => BusStatus::class,
            'last_service_date' => 'date',
            'current_mileage' => 'integer',
            'seating_capacity' => 'integer',
            'service_interval_km' => 'integer',
            'last_service_mileage' => 'integer',
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

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function scopeOperational(Builder $query): void
    {
        $query->where('status', BusStatus::Active);
    }

    public function label(): string
    {
        return $this->fleet_no ? "{$this->registration_no} ({$this->fleet_no})" : $this->registration_no;
    }

    /** Kilometres until the next routine service is due (negative when overdue). */
    public function kmToNextService(): int
    {
        $base = $this->last_service_mileage ?? 0;

        return ($base + $this->service_interval_km) - $this->current_mileage;
    }

    public function isServiceDue(int $withinKm = 500): bool
    {
        return $this->kmToNextService() <= $withinKm;
    }

    public function hasOpenMaintenance(): bool
    {
        return $this->maintenanceRecords()
            ->whereIn('status', [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress])
            ->exists();
    }

    /** Raise the odometer reading; it never goes backwards. */
    public function recordMileage(?int $odometer): void
    {
        if ($odometer !== null && $odometer > $this->current_mileage) {
            $this->forceFill(['current_mileage' => $odometer])->save();
        }
    }
}
