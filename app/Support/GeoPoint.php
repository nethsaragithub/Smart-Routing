<?php

namespace App\Support;

/**
 * A latitude/longitude pair with great-circle distance calculation.
 */
final class GeoPoint
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
    ) {
    }

    /** Straight-line (haversine) distance in kilometres. */
    public function distanceTo(self $other): float
    {
        $lat1 = deg2rad($this->latitude);
        $lat2 = deg2rad($other->latitude);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad($other->longitude - $this->longitude);

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
