<?php

namespace App\Engine\Nexorin\V6;

class DynamicTariffEngine
{
    public function price(string $region): float
    {
        return match($region) {
            'urban' => 1500,
            'suburban' => 1300,
            'rural' => 1100,
            default => 1400
        };
    }
}
