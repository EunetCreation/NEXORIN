<?php

namespace App\Engine\Nexorin\AI;

class RoofAnalyzer
{
    public function analyze(array $input): array
    {
        $area = $input['roof_area'] ?? 0;
        $efficiency = $input['installation_density'] ?? 0.75;

        $maxPv = ($area * $efficiency) / 6.5;

        return [
            'max_pv_kw' => round($maxPv, 2),
            'usable_area' => $area * $efficiency
        ];
    }
}
