<?php

namespace App\Engine\Nexorin\V4;

class HourlySimulationEngine
{
    public function simulate(float $pv, array $psh): array
    {
        $result = [];

        foreach ($psh as $month => $value) {
            $result[$month] = $pv * $value * 30 * 0.78;
        }

        return $result;
    }
}
