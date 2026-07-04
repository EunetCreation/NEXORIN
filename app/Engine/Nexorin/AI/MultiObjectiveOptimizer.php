<?php

namespace App\Engine\Nexorin\AI;

class MultiObjectiveOptimizer
{
    public function select(array $scenarios, array $context): array
    {
        $best = null;
        $bestScore = -INF;

        foreach ($scenarios as $pv) {

            $energy = $pv * ($context['psh'] ?? 4.5) * 365 * 0.78;
            $costPenalty = $pv * 900;
            $lossPenalty = abs(($context['target'] ?? 0) - $energy);

            $score = ($energy * 0.6) - ($costPenalty * 0.2) - ($lossPenalty * 0.2);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $pv;
            }
        }

        return [
            'optimal_pv' => round($best, 2),
            'score' => round($bestScore, 2)
        ];
    }
}
