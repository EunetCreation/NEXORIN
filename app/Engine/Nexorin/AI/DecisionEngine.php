<?php

namespace App\Engine\Nexorin\AI;

class DecisionEngine
{
    public function decide(array $options): array
    {
        usort($options, fn($a, $b) => $b['score'] <=> $a['score']);

        return $options[0] ?? [];
    }
}
