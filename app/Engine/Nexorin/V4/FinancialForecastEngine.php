<?php

namespace App\Engine\Nexorin\V4;

class FinancialForecastEngine
{
    public function forecast(array $monthlyEnergy, float $tariff): array
    {
        $total = array_sum($monthlyEnergy);
        $revenue = $total * $tariff;
        $capex = $total * 7500;

        return [
            'annual_energy' => $total,
            'revenue' => $revenue,
            'payback' => $capex / max($revenue, 1)
        ];
    }
}
