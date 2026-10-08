<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $fillable = [
        'bus_route_id', 'sequence', 'name', 'latitude', 'longitude',
        'distance_from_start_km', 'minutes_from_start',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_from_start_km' => 'float',
            'sequence' => 'integer',
            'minutes_from_start' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(BusRoute::class, 'bus_route_id');
    }
}
