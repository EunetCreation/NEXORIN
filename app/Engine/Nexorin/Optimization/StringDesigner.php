<?php

namespace App\Engine\Nexorin\Optimization;

class StringDesigner
{
    public function design(array $input, float $pvKw): array
    {
        $moduleWp = $input['module_power_wp'] ?? 550;
        $moduleVmp = $input['module_vmp'] ?? 41;

        $mpptMin = $input['mppt_min'] ?? 120;
        $mpptMax = $input['mppt_max'] ?? 550;

        $modulesPerString = 10;

        $vmpString = $modulesPerString * $moduleVmp;

        if ($vmpString < $mpptMin || $vmpString > $mpptMax) {
            $modulesPerString = 9;
        }

        $totalModules = ceil(($pvKw * 1000) / $moduleWp);

        $stringCount = ceil($totalModules / $modulesPerString);

        return [
            'total_modules' => $totalModules,
            'modules_per_string' => $modulesPerString,
            'string_count' => $stringCount
        ];
    }
}
