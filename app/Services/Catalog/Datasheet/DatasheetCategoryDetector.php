<?php

namespace App\Services\Catalog\Datasheet;

use App\Models\Material;
use App\Support\ProfessionalCatalogProfile;

class DatasheetCategoryDetector
{
    /**
     * Detect the professional root and, when the wording is unambiguous, the
     * exact component subtype. Exact detection is intentionally conservative:
     * it only blocks when the source clearly names a different subtype within
     * the same professional root.
     *
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function detect(string $text, Material $material, array $context = []): array
    {
        $documentName = trim((string) ($context['document_name'] ?? ''));
        $normalized = strtolower(trim($text."\n".$documentName));
        $expectedRoot = ProfessionalCatalogProfile::rootCodeFor($material);
        $expectedExact = $this->expectedExactCode($material, $expectedRoot);

        $rootResult = $this->detectRoot($normalized, $expectedRoot);
        $exactResult = $this->detectExact($normalized, $expectedRoot, $expectedExact);

        // A strongly detected exact subtype also confirms its professional root,
        // even when a degraded PDF extractor did not recover enough generic root
        // vocabulary to pass the root-only threshold.
        if (($rootResult['detected_root'] ?? null) === null && ($exactResult['detected_exact'] ?? null) !== null) {
            $rootResult['detected_root'] = $expectedRoot;
            $rootResult['score'] = max((float) ($rootResult['score'] ?? 0), min(96.0, (float) ($exactResult['score'] ?? 0)));
            $rootResult['match'] = 'matched';
            $rootResult['evidence'] = array_values(array_unique(array_merge(
                (array) ($rootResult['evidence'] ?? []),
                (array) ($exactResult['evidence'] ?? [])
            )));
        }

        $rootBlocked = (bool) ($rootResult['blocked'] ?? false);
        $exactBlocked = (bool) ($exactResult['blocked'] ?? false);

        return array_merge($rootResult, [
            'expected_root' => $expectedRoot,
            'expected_root_label' => $this->rootLabel($expectedRoot),
            'detected_root_label' => isset($rootResult['detected_root']) && $rootResult['detected_root'] !== null
                ? $this->rootLabel((string) $rootResult['detected_root'])
                : null,
            'expected_exact' => $expectedExact,
            'expected_exact_label' => $this->exactLabel($expectedRoot, $expectedExact),
            'detected_exact' => $exactResult['detected_exact'],
            'detected_exact_label' => $this->exactLabel($expectedRoot, $exactResult['detected_exact']),
            'exact_score' => $exactResult['score'],
            'exact_match' => $exactResult['match'],
            'exact_evidence' => $exactResult['evidence'],
            'root_blocked' => $rootBlocked,
            'exact_blocked' => $exactBlocked,
            'blocked' => $rootBlocked || $exactBlocked,
            'review_reason' => $this->reviewReason($rootResult, $exactResult),
        ]);
    }

    /** @return array<string,mixed> */
    private function detectRoot(string $normalized, string $expected): array
    {
        $scores = [];
        $evidence = [];

        foreach ($this->signatures() as $root => $signature) {
            $score = 0.0;
            $hits = [];
            foreach ($signature['strong'] as $keyword) {
                if (str_contains($normalized, strtolower($keyword))) {
                    $score += 3.0;
                    $hits[] = $keyword;
                }
            }
            foreach ($signature['normal'] as $keyword) {
                if (str_contains($normalized, strtolower($keyword))) {
                    $score += 1.0;
                    $hits[] = $keyword;
                }
            }
            $scores[$root] = $score;
            if ($hits !== []) {
                $evidence[$root] = array_slice(array_values(array_unique($hits)), 0, 8);
            }
        }

        arsort($scores);
        $detected = array_key_first($scores);
        $best = $detected !== null ? (float) $scores[$detected] : 0.0;
        $values = array_values($scores);
        $second = count($values) > 1 ? (float) $values[1] : 0.0;
        $confidence = $best <= 0 ? 0.0 : min(100.0, round(($best / max($best + $second, 1)) * 100, 2));

        if ($best < 4.0 || $confidence < 58.0) {
            return [
                'detected_root' => null,
                'score' => $confidence,
                'match' => 'uncertain',
                'blocked' => false,
                'evidence' => [],
            ];
        }

        $match = $detected === $expected ? 'matched' : 'mismatch';
        $blocked = $match === 'mismatch' && $confidence >= 72.0 && $best >= 7.0;

        return [
            'detected_root' => $detected,
            'score' => $confidence,
            'match' => $match,
            'blocked' => $blocked,
            'evidence' => $evidence[$detected] ?? [],
        ];
    }

    /** @return array{detected_exact:?string,score:float,match:string,blocked:bool,evidence:array<int,string>} */
    private function detectExact(string $normalized, string $root, ?string $expectedExact): array
    {
        $rootSignatures = $this->exactSignatures()[$root] ?? [];
        if ($expectedExact === null || $rootSignatures === []) {
            return [
                'detected_exact' => null,
                'score' => 0.0,
                'match' => 'not_applicable',
                'blocked' => false,
                'evidence' => [],
            ];
        }

        $scores = [];
        $evidence = [];
        foreach ($rootSignatures as $code => $phrases) {
            $score = 0.0;
            $hits = [];
            foreach ($phrases as $phrase => $weight) {
                if (str_contains($normalized, strtolower($phrase))) {
                    $score += (float) $weight;
                    $hits[] = $phrase;
                }
            }
            $scores[$code] = $score;
            if ($hits !== []) {
                $evidence[$code] = array_values(array_unique($hits));
            }
        }

        arsort($scores);
        $detected = array_key_first($scores);
        $best = $detected !== null ? (float) $scores[$detected] : 0.0;
        $values = array_values($scores);
        $second = count($values) > 1 ? (float) $values[1] : 0.0;
        $margin = max(0.0, $best - $second);

        // Do not claim an exact subtype when the source only contains generic or
        // overlapping wording. The threshold intentionally requires a strong,
        // explicit product-technology phrase.
        if ($best < 6.0 || $margin < 2.0) {
            return [
                'detected_exact' => null,
                'score' => $best <= 0 ? 0.0 : min(69.0, round(45.0 + ($best * 3.0), 2)),
                'match' => 'uncertain',
                'blocked' => false,
                'evidence' => [],
            ];
        }

        $confidence = min(99.0, round(58.0 + ($best * 4.0) + ($margin * 2.0), 2));
        $match = $detected === $expectedExact ? 'matched' : 'mismatch';
        $blocked = $match === 'mismatch' && $best >= 7.0 && $confidence >= 78.0;

        return [
            'detected_exact' => $detected,
            'score' => $confidence,
            'match' => $match,
            'blocked' => $blocked,
            'evidence' => array_slice($evidence[$detected] ?? [], 0, 6),
        ];
    }

    private function expectedExactCode(Material $material, string $root): ?string
    {
        $material->loadMissing('category.parent');
        $code = strtoupper((string) $material->category?->code);

        return $code !== '' && $code !== $root ? $code : null;
    }

    private function rootLabel(string $root): string
    {
        return (string) data_get(ProfessionalCatalogProfile::profiles(), $root.'.name', str($root)->replace('_', ' ')->title());
    }

    private function exactLabel(string $root, ?string $exact): ?string
    {
        if ($exact === null) {
            return null;
        }

        return (string) data_get(
            ProfessionalCatalogProfile::profiles(),
            $root.'.subcategories.'.$exact,
            str($exact)->replace('_', ' ')->title()
        );
    }

    /** @param array<string,mixed> $rootResult @param array<string,mixed> $exactResult */
    private function reviewReason(array $rootResult, array $exactResult): string
    {
        if ((bool) ($rootResult['blocked'] ?? false)) {
            return 'root_mismatch';
        }
        if ((bool) ($exactResult['blocked'] ?? false)) {
            return 'exact_component_mismatch';
        }
        if (($rootResult['match'] ?? 'uncertain') === 'uncertain') {
            return 'root_not_confirmed';
        }
        if (($exactResult['match'] ?? 'not_applicable') === 'uncertain') {
            return 'exact_component_not_confirmed';
        }

        return 'confirmed';
    }

    /** @return array<string,array{strong:array<int,string>,normal:array<int,string>}> */
    private function signatures(): array
    {
        return [
            'PV_MODULE' => [
                'strong' => ['open circuit voltage', 'short circuit current', 'maximum power voltage', 'module efficiency', 'pmax'],
                'normal' => ['solar module', 'pv module', 'photovoltaic module', 'voc', 'vmp', 'isc', 'imp', 'cell count'],
            ],
            'INVERTER_ON_GRID' => [
                'strong' => ['mppt voltage range', 'anti-islanding', 'rated ac output power', 'grid-tied inverter'],
                'normal' => ['string inverter', 'maximum dc input voltage', 'mppt', 'grid code', 'ac output'],
            ],
            'INVERTER_HYBRID_OFFGRID' => [
                'strong' => ['battery voltage range', 'backup output', 'eps output', 'generator input', 'hybrid inverter'],
                'normal' => ['bms protocol', 'charge current', 'discharge current', 'off-grid inverter', 'battery input'],
            ],
            'BATTERY_ESS' => [
                'strong' => ['depth of discharge', 'battery chemistry', 'cycle life', 'usable energy', 'nominal energy'],
                'normal' => ['lithium iron phosphate', 'lfp', 'battery module', 'bms', 'state of charge', 'soc'],
            ],
            'DC_ELECTRICAL' => [
                'strong' => ['iec 61643-31', 'iscpv', 'dc surge protection', 'pv fuse', 'pv cable'],
                'normal' => ['vdc', 'dc isolator', 'dc breaker', 'dc combiner', 'mc4', 'surge protective device'],
            ],
            'AC_ELECTRICAL' => [
                'strong' => ['mccb', 'ac circuit breaker', 'breaking capacity', 'trip curve'],
                'normal' => ['vac', 'ac cable', 'ats', 'sts', 'rated current', 'poles'],
            ],
            'MOUNTING_STRUCTURE' => [
                'strong' => ['wind load', 'snow load', 'roof compatibility', 'mounting rail'],
                'normal' => ['aluminium rail', 'mid clamp', 'end clamp', 'tilt angle', 'foundation type'],
            ],
            'MONITORING_METERING' => [
                'strong' => ['modbus register', 'accuracy class', 'ct ratio', 'smart meter'],
                'normal' => ['rs485', 'data logger', 'weather station', 'monitoring gateway', 'metering'],
            ],
            'GROUNDING_LIGHTNING_SAFETY' => [
                'strong' => ['earth resistance', 'lightning protection', 'grounding conductor', 'down conductor'],
                'normal' => ['earth rod', 'bonding', 'lps', 'grounding'],
            ],
            'PANEL_DISTRIBUTION' => [
                'strong' => ['acdb', 'dcdb', 'lvmdp', 'busbar rating', 'panel enclosure'],
                'normal' => ['distribution board', 'switchboard', 'panel type', 'single line diagram'],
            ],
            'CIVIL_SITE_WORK' => [
                'strong' => ['concrete grade', 'reinforcement grade', 'excavation depth', 'cable trench'],
                'normal' => ['civil work', 'foundation work', 'waterproofing', 'backfill'],
            ],
            'FIRE_HSE' => [
                'strong' => ['fire extinguisher', 'extinguishing agent', 'ppe category', 'safety signage'],
                'normal' => ['hse', 'fire safety', 'emergency response', 'first aid'],
            ],
            'LEGAL_CERTIFICATION' => [
                'strong' => ['certificate number', 'issuing authority', 'permit type', 'inspection body'],
                'normal' => ['certification service', 'permit', 'registration', 'slo'],
            ],
            'LOGISTICS_OM' => [
                'strong' => ['freight mode', 'warehouse location', 'maintenance interval', 'service response time'],
                'normal' => ['delivery terms', 'annual maintenance', 'spare parts', 'warehousing'],
            ],
            'SUPPLIER_PRICE_QUOTE' => [
                'strong' => ['unit price', 'payment terms', 'quotation validity', 'minimum order quantity'],
                'normal' => ['supplier quote', 'price list', 'lead time', 'currency', 'moq'],
            ],
        ];
    }

    /**
     * Only explicit, low-ambiguity phrases are listed. A missing exact signature
     * never blocks a document; it simply leaves the subtype unconfirmed.
     *
     * @return array<string,array<string,array<string,int|float>>>
     */
    private function exactSignatures(): array
    {
        return [
            'PV_MODULE' => [
                'PV_MONO_PERC' => ['mono perc' => 8, 'monocrystalline perc' => 8, 'p-type perc' => 7],
                'PV_TOPCON' => ['i-topcon' => 9, 'topcon' => 8, 'n-type topcon' => 9],
                'PV_HJT' => ['heterojunction' => 9, 'hjt module' => 8, 'hjt' => 7],
                'PV_BIFACIAL' => ['bifacial module' => 8, 'bifaciality' => 8, 'rear-side power' => 6],
                'PV_HIGH_POWER' => ['high-power commercial module' => 9, 'high power commercial module' => 9, 'commercial high-power module' => 8],
            ],
            'INVERTER_ON_GRID' => [
                'INV_STRING_1P' => ['single phase string inverter' => 9, 'single-phase string inverter' => 9],
                'INV_STRING_3P' => ['three phase string inverter' => 9, 'three-phase string inverter' => 9],
                'INV_MICRO' => ['microinverter' => 9, 'micro inverter' => 8],
                'INV_CENTRAL' => ['central inverter' => 9],
                'INV_OPTIMIZER' => ['module optimizer' => 9, 'power optimizer' => 8, 'mlpe' => 7],
            ],
            'INVERTER_HYBRID_OFFGRID' => [
                'INV_HYBRID' => ['hybrid inverter' => 9],
                'INV_OFFGRID' => ['off-grid inverter' => 9, 'off grid inverter' => 9],
                'INV_MPPT_CONTROLLER' => ['mppt charge controller' => 9, 'solar charge controller' => 8],
                'INV_PCS' => ['power conversion system' => 8, 'battery pcs' => 8],
            ],
            'BATTERY_ESS' => [
                'BAT_WALL' => ['wall-mounted battery' => 9, 'wall mounted battery' => 9],
                'BAT_RACK' => ['rack battery' => 9, 'rack-mounted battery' => 9, 'rack mount battery' => 8],
                'BAT_CABINET' => ['battery cabinet' => 9],
                'BAT_BMS' => ['battery management system' => 9, 'standalone bms' => 8],
                'BAT_ESS_PACKAGE' => ['ess rack package' => 8, 'ess cabinet package' => 8, 'energy storage system package' => 7],
            ],
            'DC_ELECTRICAL' => [
                'DC_CABLE_CONNECTOR' => ['pv cable' => 8, 'solar cable' => 7, 'mc4 connector' => 8],
                'DC_COMBINER' => ['dc combiner box' => 9, 'pv combiner box' => 9, 'dc junction box' => 7],
                'DC_PROTECTION' => ['dc isolator' => 8, 'pv fuse' => 8, 'dc circuit breaker' => 8],
                'DC_SPD' => ['dc surge protection' => 9, 'dc surge protective device' => 9, 'iec 61643-31' => 8],
                'DC_CONTAINMENT' => ['dc conduit' => 7, 'cable tray' => 7, 'cable termination' => 6],
            ],
            'AC_ELECTRICAL' => [
                'AC_CABLE' => ['ac power cable' => 8, 'low voltage power cable' => 7],
                'AC_PROTECTION' => ['mccb' => 8, 'acb' => 8, 'ac circuit breaker' => 8],
                'AC_TRANSFER_SYNC' => ['automatic transfer switch' => 9, 'ats controller' => 8, 'static transfer switch' => 8, 'amf controller' => 8],
                'AC_METERING_CONTROL' => ['export control meter' => 8, 'energy meter' => 7, 'current transformer' => 7],
                'AC_TRANSFORMER_MV' => ['distribution transformer' => 8, 'medium voltage transformer' => 9, 'mv switchgear' => 7],
            ],
            'MOUNTING_STRUCTURE' => [
                'MOUNT_ROOF' => ['rooftop mounting' => 8, 'roof mounting rail' => 8, 'mid clamp' => 7],
                'MOUNT_BALLAST' => ['ballasted mounting' => 9, 'ballast mounting' => 8],
                'MOUNT_GROUND' => ['ground-mounted structure' => 9, 'ground mount structure' => 9],
                'MOUNT_CARPORT' => ['solar carport' => 9, 'carport structure' => 8],
                'MOUNT_ACCESS' => ['lifeline system' => 8, 'roof walkway' => 8, 'roof access system' => 7],
            ],
            'MONITORING_METERING' => [
                'MON_DATA_LOGGER' => ['data logger' => 8, 'monitoring gateway' => 8],
                'MON_METER' => ['smart meter' => 9, 'energy meter' => 8],
                'MON_NETWORK' => ['industrial router' => 8, 'network switch' => 8],
                'MON_WEATHER' => ['weather station' => 9, 'pyranometer' => 9],
                'MON_SENSOR' => ['ambient temperature sensor' => 8, 'module temperature sensor' => 8, 'environmental sensor' => 7],
            ],
            'GROUNDING_LIGHTNING_SAFETY' => [
                'GROUNDING_SYSTEM' => ['grounding electrode' => 8, 'earth rod' => 8, 'grounding conductor' => 8],
                'LIGHTNING_PROTECTION' => ['lightning protection system' => 9, 'air terminal' => 7],
                'BONDING' => ['equipotential bonding' => 9, 'bonding conductor' => 8],
                'SAFETY_LABEL_LOTO' => ['lockout tagout' => 9, 'loto' => 8, 'warning label' => 7],
            ],
            'PANEL_DISTRIBUTION' => [
                'PANEL_DCDB' => ['dc distribution board' => 9, 'dcdb' => 9],
                'PANEL_ACDB' => ['ac distribution board' => 9, 'acdb' => 9],
                'PANEL_BACKUP' => ['critical load panel' => 9, 'backup load panel' => 9],
                'PANEL_LVMDP' => ['lvmdp' => 9, 'low voltage main distribution panel' => 9],
                'PANEL_SYNC' => ['synchronization panel' => 9, 'synchronising panel' => 8],
            ],
            'CIVIL_SITE_WORK' => [
                'CIVIL_ANCHOR_SEALANT' => ['chemical anchor' => 8, 'waterproof sealant' => 8],
                'CIVIL_FOUNDATION' => ['equipment foundation' => 8, 'concrete foundation' => 8],
                'CIVIL_TRENCH_DUCT' => ['cable trench' => 9, 'duct bank' => 9],
                'CIVIL_SURVEY' => ['site survey' => 8, 'topographic survey' => 8],
                'CIVIL_BUILDING_WORK' => ['equipment shelter' => 9, 'site facility' => 7],
            ],
            'FIRE_HSE' => [
                'FIRE_EXTINGUISHER' => ['fire extinguisher' => 9],
                'FIRE_DETECTION' => ['smoke detector' => 8, 'heat detector' => 8, 'gas detector' => 8],
                'FIRE_SUPPRESSION' => ['fixed fire suppression' => 9, 'clean agent system' => 8],
                'HSE_PPE' => ['personal protective equipment' => 9, 'ppe equipment' => 8],
                'HSE_SERVICE' => ['hse procedure' => 8, 'hse service' => 8],
            ],
            'LEGAL_CERTIFICATION' => [
                'LEGAL_SLO_INSPECTION' => ['slo inspection' => 9, 'technical inspection' => 7],
                'LEGAL_UTILITY' => ['utility interconnection' => 9, 'grid interconnection application' => 8],
                'LEGAL_CERTIFICATE' => ['product certification' => 8, 'system certification' => 8],
                'LEGAL_PERMIT' => ['permit application' => 8, 'registration service' => 7],
            ],
            'LOGISTICS_OM' => [
                'LOG_FREIGHT' => ['freight service' => 8, 'delivery service' => 7],
                'LOG_STORAGE' => ['warehousing' => 8, 'site storage' => 8],
                'OM_TEST_EQUIPMENT' => ['insulation tester' => 8, 'iv curve tracer' => 8, 'test equipment' => 6],
                'OM_SERVICE' => ['annual o&m service' => 9, 'annual maintenance service' => 8, 'preventive maintenance service' => 8],
                'OM_SPARE_WARRANTY' => ['spare parts support' => 8, 'warranty support service' => 8, 'spare parts and warranty support' => 9],
            ],
            'SUPPLIER_PRICE_QUOTE' => [
                'QUOTE_MATERIAL' => ['material supplier quote' => 8, 'product quotation' => 7],
                'QUOTE_SERVICE' => ['service provider quote' => 8, 'service quotation' => 7],
                'QUOTE_PRICELIST' => ['supplier pricelist' => 9, 'price list' => 7],
            ],
        ];
    }
}
