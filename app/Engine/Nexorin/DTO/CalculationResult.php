<?php

namespace App\Engine\Nexorin\DTO;

class CalculationResult
{
    public function __construct(
        public string $system,
        public float $pv_size,
        public float $annual_energy,
        public array $inverter = [],
        public array $string = [],
        public array $financial = []
    ) {}
}
