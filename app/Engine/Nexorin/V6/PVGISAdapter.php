<?php

namespace App\Engine\Nexorin\V6;

class PVGISAdapter
{
    public function getData(string $location): array
    {
        return [
            'location' => $location,
            'irradiance' => 5.1,
            'temperature' => 30,
            'season_factor' => 0.92
        ];
    }
}
