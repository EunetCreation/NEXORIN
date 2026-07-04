<?php

namespace App\Engine\Nexorin\V5;

class FinalDecisionEngine
{
    public function decide(array $options): array
    {
        usort($options, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        return $options[0] ?? [];
    }
}
