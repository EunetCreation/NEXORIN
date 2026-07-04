<?php

namespace App\Engine\Nexorin\V5;

class AIFinancialForecast
{
    public function forecast(float $pv, array $weather): array
    {
        $energy = $pv * $weather['irradiance'] * 365 * $weather['cloud_factor'];

        $revenue = $energy * 1500;
        $capex = $pv * 8500000;

        return [
            'energy' => $energy,
            'revenue' => $revenue,
            'payback' => $capex / max($revenue, 1)
        ];
    }
}
