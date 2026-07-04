<?php

namespace App\Engine\Nexorin\V5;

class UncertaintyModel
{
    public function score(array $scenarios): array
    {
        $results = [];

        foreach ($scenarios as $pv) {

            $risk = abs($pv - 3.5) * 0.1;
            $confidence = max(0, 1 - $risk);

            $results[] = [
                'pv' => $pv,
                'confidence' => $confidence
            ];
        }

        return $results;
    }
}
