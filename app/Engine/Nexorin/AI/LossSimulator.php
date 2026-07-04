<?php

namespace App\Engine\Nexorin\AI;

class LossSimulator
{
    public function simulate(float $pv): float
    {
        $baseLoss = 0.78;
        $shadePenalty = $pv > 5 ? 0.95 : 1.0;

        return $baseLoss * $shadePenalty;
    }
}
