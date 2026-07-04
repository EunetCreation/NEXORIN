<?php

namespace App\Engine\Nexorin\Financial;

class FinancialEngine
{
    public function calculate(array $input, float $energy): array
    {
        $tariff = $input['electricity_tariff'] ?? 1500;

        $annualSaving = $energy * $tariff;

        $capex = $input['system_cost'] ?? ($energy * 8000);

        $payback = $capex / max($annualSaving, 1);

        $lcoe = $capex / max($energy * 25, 1);

        return [
            'annual_saving' => $annualSaving,
            'payback_years' => round($payback, 2),
            'lcoe' => round($lcoe, 2)
        ];
    }
}
