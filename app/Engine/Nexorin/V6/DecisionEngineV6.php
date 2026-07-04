<?php

namespace App\Engine\Nexorin\V6;

class DecisionEngineV6
{
    public function decide(array $options): array
    {
        usort($options, fn($a,$b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        return $options[0] ?? [];
    }
}
