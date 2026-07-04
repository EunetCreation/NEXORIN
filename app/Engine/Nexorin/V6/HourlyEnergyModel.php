<?php

namespace App\Engine\Nexorin\V6;

class HourlyEnergyModel
{
    public function simulate(float $pv, float $irradiance, float $shade): array
    {
        $hours = range(1, 12);
        $out = [];

        foreach ($hours as $m) {
            $out[$m] = $pv * $irradiance * 30 * $shade * (0.9 + rand(0,10)/100);
        }

        return $out;
    }
}
