<?php

namespace App\Service;

/**
 * Rough, clearly-approximate public transport travel time, used only when
 * no TBM line/stop is close enough to a destination to show real schedules.
 * NOT a real routing engine - just straight-line distance over an assumed
 * average tram/bus speed, plus a fixed walk+wait overhead.
 */
class TransitTimeEstimator
{
    private const AVERAGE_SPEED_KMH = 18.0;
    private const OVERHEAD_MINUTES = 8;

    public function estimateMinutes(float $fromLat, float $fromLng, float $toLat, float $toLng): int
    {
        $distanceKm = $this->haversineKm($fromLat, $fromLng, $toLat, $toLng);
        $travelMinutes = ($distanceKm / self::AVERAGE_SPEED_KMH) * 60;

        return (int) ceil($travelMinutes + self::OVERHEAD_MINUTES);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
