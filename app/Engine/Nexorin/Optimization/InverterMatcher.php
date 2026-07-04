<?php

namespace App\Engine\Nexorin\Optimization;

class InverterMatcher
{
    public function match(float $pvKw, array $inverters): array
    {
        $best = null;
        $bestScore = -INF;

        foreach ($inverters as $inv) {

            $capacity = $inv['capacity_kw'] ?? 0;

            if ($capacity <= 0) continue;

            $ratio = $pvKw / $capacity;

            if ($ratio < 0.8 || $ratio > 1.3) continue;

            $score = 1 - abs(1 - $ratio);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $inv;
            }
        }

        return [
            'inverter' => $best,
            'score' => $bestScore
        ];
    }
}
