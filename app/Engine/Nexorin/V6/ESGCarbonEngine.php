<?php

namespace App\Engine\Nexorin\V6;

class ESGCarbonEngine
{
    public function calculate(float $energy): array
    {
        $carbonFactor = 0.82;

        return [
            'co2_reduction' => $energy * $carbonFactor,
            'esg_score' => min(100, ($energy / 10000) * 100)
        ];
    }
}
