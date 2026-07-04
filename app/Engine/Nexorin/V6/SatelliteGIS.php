<?php

namespace App\Engine\Nexorin\V6;

class SatelliteGIS
{
    public function analyzeRoof(array $coords): array
    {
        return [
            'usable_area' => 120,
            'tilt' => 15,
            'azimuth' => 180,
            'shade_factor' => 0.88
        ];
    }
}
