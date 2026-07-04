<?php

namespace App\Engine\Nexorin\V4;

class DegradationModel
{
    public function apply(float $energy): float
    {
        $degradationRate = 0.985;
        return $energy * pow($degradationRate, 1);
    }
}
