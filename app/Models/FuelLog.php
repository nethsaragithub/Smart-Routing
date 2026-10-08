<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToDepot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    use BelongsToDepot, HasFactory;

    protected $fillable = [
        'depot_id', 'bus_id', 'driver_id', 'bus_route_id', 'filled_on', 'odometer', 'litres',
        'price_per_litre', 'total_cost', 'full_tank', 'station', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'filled_on' => DateOnly::class,
            'odometer' => 'integer',
            'litres' => 'float',
            'price_per_litre' => 'float',
            'total_cost' => 'float',
            'full_tank' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Total cost is always derived, so it can never disagree with litres x price.
        static::saving(function (FuelLog $log) {
            $log->total_cost = round($log->litres * $log->price_per_litre, 2);
        });
    }

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(BusRoute::class, 'bus_route_id')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
