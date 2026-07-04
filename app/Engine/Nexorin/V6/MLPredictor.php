<?php

namespace App\Engine\Nexorin\V6;

class MLPredictor
{
    public function predict(array $input): array
    {
        $base = $input['load'] / 4.8;

        return [
            'pv_low' => $base * 0.85,
            'pv_mid' => $base,
            'pv_high' => $base * 1.25
        ];
    }
}
