<?php

namespace App\Engine\Nexorin\V4;

class BatteryDispatchEngine
{
    public function dispatch(array $energy, float $demand): array
    {
        $battery = 0;
        $import = [];
        $export = [];

        foreach ($energy as $month => $value) {

            if ($value > $demand) {
                $export[$month] = $value - $demand;
                $battery += ($value - $demand) * 0.3;
            } else {
                $import[$month] = $demand - $value;
            }
        }

        return [
            'battery_level' => $battery,
            'import' => $import,
            'export' => $export
        ];
    }
}
