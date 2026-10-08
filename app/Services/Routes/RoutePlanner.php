<?php

namespace App\Services\Routes;

use App\Models\BusRoute;
use App\Support\GeoPoint;
use Illuminate\Support\Facades\DB;

/**
 * Saves a route together with its ordered list of stops, and estimates
 * each stop's distance and running time from the start of the route.
 */
class RoutePlanner
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{name: string, lat: float|string, lng: float|string}>  $stops
     */
    public function save(BusRoute $route, array $attributes, array $stops): BusRoute
    {
        return DB::transaction(function () use ($route, $attributes, $stops) {
            $route->fill($attributes)->save();
            $this->syncStops($route, $stops);

            return $route->load('stops');
        });
    }

    /**
     * @param  list<array{name: string, lat: float|string, lng: float|string}>  $stops
     */
    private function syncStops(BusRoute $route, array $stops): void
    {
        $route->stops()->delete();

        $points = array_map(fn ($s) => new GeoPoint((float) $s['lat'], (float) $s['lng']), $stops);
        $cumulative = [0.0];

        for ($i = 1; $i < count($points); $i++) {
            $cumulative[$i] = $cumulative[$i - 1] + $points[$i - 1]->distanceTo($points[$i]);
        }

        // Straight-line distances are shorter than road distances, so scale
        // them to the route's real length before estimating timings.
        $straightTotal = end($cumulative) ?: 0.0;
        $scale = $straightTotal > 0 ? $route->distance_km / $straightTotal : 0;

        foreach ($stops as $i => $stop) {
            $km = round($cumulative[$i] * $scale, 2);

            $route->stops()->create([
                'sequence' => $i + 1,
                'name' => trim($stop['name']),
                'latitude' => (float) $stop['lat'],
                'longitude' => (float) $stop['lng'],
                'distance_from_start_km' => $km,
                'minutes_from_start' => $route->distance_km > 0
                    ? (int) round($km / $route->distance_km * $route->estimated_duration_minutes)
                    : null,
            ]);
        }
    }
}
