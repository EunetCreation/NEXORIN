<?php

namespace App\Engine\Nexorin\V5;

class DesignPredictor
{
    public function predict(array $weather, float $load): array
    {
        $irr = $weather['irradiance'];
        $cloud = $weather['cloud_factor'];

        $basePv = $load / ($irr * $cloud);

        $candidates = [
            $basePv * 0.85,
            $basePv,
            $basePv * 1.2
        ];

        return [
            'candidates' => $candidates
        ];
    }
}
