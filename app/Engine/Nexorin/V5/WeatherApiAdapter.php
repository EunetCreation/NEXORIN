<?php

namespace App\Engine\Nexorin\V5;

class WeatherApiAdapter
{
    public function fetch(string $location): array
    {
        return [
            'irradiance' => rand(4, 6),
            'temperature' => rand(25, 35),
            'cloud_factor' => rand(70, 95) / 100
        ];
    }
}
