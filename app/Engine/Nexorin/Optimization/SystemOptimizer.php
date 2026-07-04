<?php

namespace App\Engine\Nexorin\Optimization;

class SystemOptimizer
{
    public function optimize(array $input): array
    {
        $load = $input['load'] ?? 0;
        $psh = $input['psh'] ?? 4.5;

        $basePv = $load / 4.5;

        $options = [
            $basePv * 0.9,
            $basePv,
            $basePv * 1.1
        ];

        $best = null;
        $bestScore = -INF;

        foreach ($options as $pv) {

            $energy = $pv * $psh * 365 * 0.8;

            $lossPenalty = abs($load * 365 - $energy);

            $score = $energy - $lossPenalty;

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $pv;
            }
        }

        return [
            'optimal_pv_kw' => round($best, 2),
            'score' => round($bestScore, 2),
        ];
    }
}
