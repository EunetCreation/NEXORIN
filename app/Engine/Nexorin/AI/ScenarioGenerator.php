<?php

namespace App\Engine\Nexorin\AI;

class ScenarioGenerator
{
    public function generate(float $basePv): array
    {
        return [
            $basePv * 0.8,
            $basePv,
            $basePv * 1.15
        ];
    }
}
