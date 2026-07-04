<?php

namespace App\Engine\Nexorin\Core;

use App\Engine\Nexorin\Exceptions\ConstraintViolationException;

class ConstraintEngine
{
    public function validate(array $input, string $system): void
    {
        $this->validateMPPT($input);

        if ($system === 'ON_GRID') {
            $this->validateOnGrid($input);
        }

        if ($system === 'OFF_GRID') {
            $this->validateBattery($input);
        }
    }

    private function validateMPPT($input): void
    {
        $vmp = $input['vmp'] ?? 0;
        $current = $input['current'] ?? 0;

        if ($vmp < 120 || $vmp > 550) {
            throw new \Exception('MPPT voltage out of range');
        }

        if ($current > 32) {
            throw new \Exception('Input current exceeds inverter limit');
        }
    }

    private function validateOnGrid($input): void
    {
        if (!empty($input['battery_capacity']) && $input['battery_capacity'] > 0) {
            throw new \Exception('Battery must be disabled for on-grid system');
        }
    }

    private function validateBattery($input): void
    {
        if (empty($input['battery_efficiency']) || empty($input['dod'])) {
            throw new \Exception('Battery parameters required');
        }
    }
}
