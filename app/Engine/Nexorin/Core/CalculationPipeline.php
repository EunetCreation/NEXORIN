<?php

namespace App\Engine\Nexorin\Core;

use App\Engine\Nexorin\DTO\CalculationResult;
use App\Engine\Nexorin\Optimization\SystemOptimizer;
use App\Engine\Nexorin\Optimization\InverterMatcher;
use App\Engine\Nexorin\Optimization\StringDesigner;
use App\Engine\Nexorin\Financial\FinancialEngine;

class CalculationPipeline
{
    public function __construct(
        private SystemResolver $resolver,
        private ConstraintEngine $constraint,
        private SystemOptimizer $optimizer,
        private InverterMatcher $inverterMatcher,
        private StringDesigner $stringDesigner,
        private FinancialEngine $financial
    ) {}

    public function run(array $input): CalculationResult
    {
        $system = $this->resolver->resolve($input);

        $this->constraint->validate($input, $system);

        $opt = $this->optimizer->optimize($input);
        $pv = $opt['optimal_pv_kw'] ?? 0;

        $inverter = $this->inverterMatcher->match($pv, $input['inverters'] ?? []);

        $string = $this->stringDesigner->design($input, $pv);

        $energy = $pv * ($input['psh'] ?? 4.5) * 365 * 0.8;

        $financial = $this->financial->calculate($input, $energy);

        return new CalculationResult(
            system: $system,
            pv_size: $pv,
            annual_energy: round($energy, 2),
            inverter: $inverter,
            string: $string,
            financial: $financial
        );
    }
}
