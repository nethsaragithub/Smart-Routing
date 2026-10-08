<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Models\Concerns\BelongsToDepot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bus route (named BusRoute to avoid clashing with Laravel's Route facade).
 */
class BusRoute extends Model
{
    use BelongsToDepot, HasFactory, SoftDeletes;

    protected $fillable = [
        'depot_id', 'route_no', 'name', 'origin', 'destination', 'distance_km',
        'estimated_duration_minutes', 'service_type', 'min_capacity', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
            'estimated_duration_minutes' => 'integer',
            'min_capacity' => 'integer',
            'service_type' => ServiceType::class,
            'is_active' => 'boolean',
        ];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence');
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
        $query->where('is_active', true);
    }

    public function label(): string
    {
        return "{$this->route_no} · {$this->origin} – {$this->destination}";
    }

    public function durationLabel(): string
    {
        $h = intdiv($this->estimated_duration_minutes, 60);
        $m = $this->estimated_duration_minutes % 60;

        return $h ? sprintf('%dh %02dm', $h, $m) : "{$m} min";
    }

    /**
     * Stops in the shape the map component expects.
     *
     * @return list<array{name: string, lat: float, lng: float}>
     */
    public function stopsForMap(): array
    {
        return $this->stops->map(fn (RouteStop $stop) => [
            'name' => $stop->name,
            'lat' => (float) $stop->latitude,
            'lng' => (float) $stop->longitude,
        ])->values()->all();
    }
}
