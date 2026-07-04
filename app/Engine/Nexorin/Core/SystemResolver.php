<?php

namespace App\Engine\Nexorin\Core;

class SystemResolver
{
    public function resolve(array $input): string
    {
        return match ($input['system_type'] ?? '') {
            'on-grid' => 'ON_GRID',
            'off-grid' => 'OFF_GRID',
            'hybrid' => 'HYBRID',
            default => throw new \Exception('Invalid system type')
        };
    }
}
