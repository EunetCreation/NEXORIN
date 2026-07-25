<?php

namespace App\Services\Catalog\Datasheet;

use App\Models\Material;
use App\Support\ProfessionalCatalogProfile;

class PvVariantTableParser
{
    /**
     * Parse STC multi-variant PV module tables and governed PV-only values.
     *
     * @param array<int,array{page:int,text:string}> $pages
     * @param array<string,mixed> $context
     * @return array{detected:bool,blocked:bool,selection_required:bool,available_variants:array<int,float>,selected_variant:?float,selection_source:?string,fields:array<string,array<string,mixed>>,warnings:array<int,string>}
     */
    public function parse(array $pages, Material $material, array $context = []): array
    {
        if (ProfessionalCatalogProfile::rootCodeFor($material) !== 'PV_MODULE') {
            return $this->emptyResult();
        }

        $matrix = $this->parseStcMatrix($pages);
        $supplemental = $this->parseSupplementalFields($pages);
        $warnings = [];

        if ($matrix === null) {
            return [
                'detected' => false,
                'blocked' => false,
                'selection_required' => false,
                'available_variants' => [],
                'selected_variant' => null,
                'selection_source' => null,
                'fields' => $supplemental,
                'warnings' => [],
            ];
        }

        $available = array_map('floatval', array_keys($matrix['variants']));
        sort($available, SORT_NUMERIC);
        $selection = $this->selectVariant($available, $material, $context);
        $blocked = (bool) $selection['blocked'];
        $selected = $selection['selected_variant'];
        $fields = $supplemental;

        if ($selected !== null && isset($matrix['variants'][$this->variantKey($selected)])) {
            $variant = $matrix['variants'][$this->variantKey($selected)];
            foreach ($variant as $key => $value) {
                $fields[$key] = [
                    'raw_value' => (string) $value['value'],
                    'raw_unit' => $value['unit'],
                    'page' => $matrix['page'],
                    'excerpt' => $value['excerpt'],
                    'alias' => 'STC matrix · '.$this->formatPower($selected).' Wp',
                    'base_confidence' => 0.98,
                    'warnings' => [],
                ];
            }
        } elseif (count($available) > 1) {
            $warnings[] = 'This datasheet contains multiple STC product variants ('.implode(', ', array_map(fn (float $power): string => $this->formatPower($power).' Wp', $available)).'). Select the exact material power before applying variant-specific electrical values.';
        }

        if ($selection['reason'] !== null) {
            $warnings[] = $selection['reason'];
        }

        return [
            'detected' => true,
            'blocked' => $blocked,
            'selection_required' => $selected === null && count($available) > 1,
            'available_variants' => $available,
            'selected_variant' => $selected,
            'selection_source' => $selection['source'],
            'fields' => $fields,
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    /** @return array<string,mixed>|null */
    private function parseStcMatrix(array $pages): ?array
    {
        foreach ($pages as $page) {
            $text = (string) ($page['text'] ?? '');
            if (stripos($text, 'ELECTRICAL DATA') === false || stripos($text, 'STC') === false) {
                continue;
            }

            $section = $this->section($text, '/ELECTRICAL\s+DATA\s*\(\s*STC\s*\)/i', '/ELECTRICAL\s+DATA\s*\(\s*NOCT\s*\)/i');
            if ($section === null) {
                continue;
            }

            $rows = [
                'module_power_wp' => ['pattern' => '/Peak\s+Power\s+Watts[^\n]*/i', 'unit' => 'W'],
                'vmp' => ['pattern' => '/Maximum\s+Power\s+Voltage[^\n]*/i', 'unit' => 'V'],
                'imp' => ['pattern' => '/Maximum\s+Power\s+Current[^\n]*/i', 'unit' => 'A'],
                'voc' => ['pattern' => '/Open\s+Circuit\s+Voltage[^\n]*/i', 'unit' => 'V'],
                'isc' => ['pattern' => '/Short\s+Circuit\s+Current[^\n]*/i', 'unit' => 'A'],
                'module_efficiency_percent' => ['pattern' => '/Module\s+Efficiency[^\n]*/i', 'unit' => '%'],
            ];

            $parsedRows = [];
            foreach ($rows as $key => $row) {
                if (preg_match($row['pattern'], $section, $match) !== 1) {
                    continue;
                }
                $values = $this->numbersAfterLabel($match[0]);
                if ($values === []) {
                    continue;
                }
                $parsedRows[$key] = [
                    'values' => $values,
                    'unit' => $row['unit'],
                    'excerpt' => trim($match[0]),
                ];
            }

            $powers = $parsedRows['module_power_wp']['values'] ?? [];
            if (count($powers) < 2) {
                continue;
            }

            $variants = [];
            foreach ($powers as $index => $power) {
                $variantKey = $this->variantKey($power);
                $variants[$variantKey] = [];
                foreach ($parsedRows as $fieldKey => $row) {
                    if (! array_key_exists($index, $row['values'])) {
                        continue;
                    }
                    $variants[$variantKey][$fieldKey] = [
                        'value' => $row['values'][$index],
                        'unit' => $row['unit'],
                        'excerpt' => $row['excerpt'],
                    ];
                }
            }

            return [
                'page' => (int) ($page['page'] ?? 1),
                'variants' => $variants,
            ];
        }

        return null;
    }

    /** @return array<string,array<string,mixed>> */
    private function parseSupplementalFields(array $pages): array
    {
        $fields = [];

        foreach ($pages as $page) {
            $pageNumber = (int) ($page['page'] ?? 1);
            $text = (string) ($page['text'] ?? '');
            $lines = preg_split('/\n+/', $text) ?: [];

            foreach ($lines as $line) {
                $clean = trim($line);
                if ($clean === '') {
                    continue;
                }

                $this->captureSignedCoefficient($fields, 'temp_coeff_pmax', $clean, '/Temperature\s+Coefficient(?:\s+of)?\s+P(?:MAX|MAXIMUM)/i', $pageNumber, 'temperature coefficient of pmax');
                $this->captureSignedCoefficient($fields, 'temp_coeff_voc', $clean, '/Temperature\s+Coefficient(?:\s+of)?\s+VOC/i', $pageNumber, 'temperature coefficient of voc');

                if (! isset($fields['cell_count']) && preg_match('/(?:No\.?\s+of\s+cells|Number\s+of\s+cells|Cell\s+Count)\s*[:\-]?\s*(\d{2,4})\s*cells?/i', $clean, $match) === 1) {
                    $fields['cell_count'] = $this->candidate($match[1], 'cells', $pageNumber, $clean, 'number of cells', 0.97);
                }

                if (! isset($fields['cell_type']) && preg_match('/(?:Solar\s+Cells?|Cell\s+Technology|Cell\s+Type)\s*[:\-]?\s*(.+)$/i', $clean, $match) === 1) {
                    $value = trim($match[1]);
                    if ($value !== '' && ! preg_match('/^\d+\s*cells?$/i', $value)) {
                        $fields['cell_type'] = $this->candidate($value, null, $pageNumber, $clean, 'solar cells', 0.95);
                    }
                }

                if (! isset($fields['weight_kg']) && preg_match('/(?:Module\s+Weight|Weight)\s*[:\-]?\s*(\d+(?:[.,]\d+)?)\s*kg\b/i', $clean, $match) === 1) {
                    $fields['weight_kg'] = $this->candidate($match[1], 'kg', $pageNumber, $clean, 'module weight', 0.97);
                }

                if (! isset($fields['max_system_voltage']) && preg_match('/Maximum\s+System\s+Voltage\s*[:\-]?\s*(\d+(?:[.,]\d+)?)\s*V(?:DC)?\b/i', $clean, $match) === 1) {
                    $fields['max_system_voltage'] = $this->candidate($match[1], 'V', $pageNumber, $clean, 'maximum system voltage', 0.98);
                }

                if (! isset($fields['first_year_degradation_percent']) && preg_match('/(\d+(?:[.,]\d+)?)\s*%\s*(?:first\s+year\s+)?degradation/i', $clean, $match) === 1) {
                    $fields['first_year_degradation_percent'] = $this->candidate($match[1], '%', $pageNumber, $clean, 'first year degradation', 0.93);
                }

                if (! isset($fields['annual_degradation_percent']) && preg_match('/(\d+(?:[.,]\d+)?)\s*%\s*(?:annual\s+power\s+)?(?:attenuation|annual\s+degradation)/i', $clean, $match) === 1) {
                    $fields['annual_degradation_percent'] = $this->candidate($match[1], '%', $pageNumber, $clean, 'annual degradation', 0.93);
                }

                if (! isset($fields['connector_type']) && preg_match('/^Connector\s*[:\-]?\s*(.+)$/i', $clean, $match) === 1) {
                    $fields['connector_type'] = $this->candidate(trim($match[1]), null, $pageNumber, $clean, 'connector', 0.91);
                }

                if (preg_match('/(\d{3,5}(?:[.,]\d+)?)\s*[x×]\s*(\d{3,5}(?:[.,]\d+)?)\s*[x×]\s*(\d{1,4}(?:[.,]\d+)?)\s*mm\b/iu', $clean, $match) === 1) {
                    $fields['length_mm'] ??= $this->candidate($match[1], 'mm', $pageNumber, $clean, 'module dimensions length', 0.98);
                    $fields['width_mm'] ??= $this->candidate($match[2], 'mm', $pageNumber, $clean, 'module dimensions width', 0.98);
                }

                if (! isset($fields['product_warranty_years']) && preg_match('/(\d{1,3})\s*[- ]?year\s+Product\s+Warranty/i', $clean, $match) === 1) {
                    $fields['product_warranty_years'] = $this->candidate($match[1], 'years', $pageNumber, $clean, 'product warranty', 0.99);
                }

                if (! isset($fields['performance_warranty_years']) && preg_match('/(\d{1,3})\s*[- ]?year\s+(?:Power\s+Guarantee|Performance\s+Warranty|Power\s+Warranty)/i', $clean, $match) === 1) {
                    $fields['performance_warranty_years'] = $this->candidate($match[1], 'years', $pageNumber, $clean, 'performance warranty', 0.99);
                }
            }

            if (! isset($fields['cell_type']) && preg_match('/\b(N-type|P-type)\s+([A-Za-z0-9\- ]{2,40}?(?:TOPCon|PERC|HJT)[A-Za-z0-9\- ]*)\b/i', $text, $match) === 1) {
                $fields['cell_type'] = $this->candidate(trim($match[1].' '.$match[2]), null, $pageNumber, trim($match[0]), 'cell technology', 0.93);
            }

            if (! isset($fields['product_warranty_years']) && preg_match('/(\d{1,3})\s*[- ]?year\s+Product\s+Warranty/i', $text, $match) === 1) {
                $fields['product_warranty_years'] = $this->candidate($match[1], 'years', $pageNumber, trim($match[0]), 'product warranty', 0.99);
            }
            if (! isset($fields['performance_warranty_years']) && preg_match('/(\d{1,3})\s*[- ]?year\s+(?:Power\s+Guarantee|Performance\s+Warranty|Power\s+Warranty)/i', $text, $match) === 1) {
                $fields['performance_warranty_years'] = $this->candidate($match[1], 'years', $pageNumber, trim($match[0]), 'performance warranty', 0.99);
            }
        }

        return $fields;
    }

    /** @param array<string,array<string,mixed>> $fields */
    private function captureSignedCoefficient(array &$fields, string $key, string $line, string $labelPattern, int $page, string $alias): void
    {
        if (isset($fields[$key]) || preg_match($labelPattern, $line) !== 1) {
            return;
        }

        if (preg_match('/([+\-−]?)\s*(\d+(?:[.,]\d+)?)\s*%\s*\/\s*[^\s]+/u', $line, $match) !== 1) {
            return;
        }

        $sign = str_replace('−', '-', $match[1]);
        $value = ($sign === '-' ? '-' : ($sign === '+' ? '+' : '')).$match[2];
        $fields[$key] = $this->candidate($value, '%/°C', $page, $line, $alias, 0.98);
    }

    /** @return array{selected_variant:?float,source:?string,blocked:bool,reason:?string} */
    private function selectVariant(array $available, Material $material, array $context): array
    {
        $requested = isset($context['variant_power_wp']) && $context['variant_power_wp'] !== ''
            ? (float) $context['variant_power_wp']
            : null;
        if ($requested !== null) {
            $match = $this->exactAvailable($available, $requested);
            if ($match !== null) {
                return ['selected_variant' => $match, 'source' => 'user_selected', 'blocked' => false, 'reason' => null];
            }

            return [
                'selected_variant' => null,
                'source' => 'user_selected',
                'blocked' => true,
                'reason' => 'The requested '.$this->formatPower($requested).' Wp variant is not present in this datasheet. Choose one of the detected STC variants.',
            ];
        }

        $existingSpec = $material->relationLoaded('specs')
            ? $material->specs->firstWhere('spec_key', 'module_power_wp')?->spec_value
            : $material->specs()->where('spec_key', 'module_power_wp')->value('spec_value');
        $candidates = [
            ['value' => $existingSpec, 'source' => 'existing_spec'],
            ['value' => $material->nominal_power_wp, 'source' => 'material_summary'],
        ];

        foreach ([$material->model, $material->sku, $material->name] as $identity) {
            foreach ($this->powerTokens((string) $identity) as $token) {
                $candidates[] = ['value' => $token, 'source' => 'material_identity'];
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate['value'] === null || $candidate['value'] === '') {
                continue;
            }
            $match = $this->exactAvailable($available, (float) $candidate['value']);
            if ($match !== null) {
                return ['selected_variant' => $match, 'source' => $candidate['source'], 'blocked' => false, 'reason' => null];
            }
        }

        if (count($available) === 1) {
            return ['selected_variant' => $available[0], 'source' => 'single_variant', 'blocked' => false, 'reason' => null];
        }

        $declared = collect($candidates)->pluck('value')->filter(fn ($value): bool => $value !== null && $value !== '')->first();
        if ($declared !== null) {
            return [
                'selected_variant' => null,
                'source' => null,
                'blocked' => true,
                'reason' => 'The material is currently identified as '.$this->formatPower((float) $declared).' Wp, but that value is not present in the detected STC variant table.',
            ];
        }

        return ['selected_variant' => null, 'source' => null, 'blocked' => true, 'reason' => null];
    }

    /** @return array<int,float> */
    private function powerTokens(string $value): array
    {
        if ($value === '') {
            return [];
        }
        preg_match_all('/(?<!\d)(\d{3,4}(?:[.,]\d+)?)\s*W(?:p)?\b/i', $value, $matches);

        return collect($matches[1] ?? [])->map(fn (string $token): float => (float) str_replace(',', '.', $token))->unique()->values()->all();
    }

    private function exactAvailable(array $available, float $target): ?float
    {
        foreach ($available as $power) {
            if (abs($power - $target) <= 0.05) {
                return (float) $power;
            }
        }

        return null;
    }

    /** @return array<int,float> */
    private function numbersAfterLabel(string $line): array
    {
        preg_match_all('/[-+]?\d+(?:[.,]\d+)?/', $line, $matches);
        $numbers = array_map(fn (string $value): float => (float) str_replace(',', '.', $value), $matches[0] ?? []);

        // Remove numbers that are part of labels such as PMAX or units; only realistic
        // PV table values remain. For the Pmax row this keeps the product powers.
        return array_values(array_filter($numbers, fn (float $value): bool => is_finite($value)));
    }

    private function section(string $text, string $startPattern, string $endPattern): ?string
    {
        if (preg_match($startPattern, $text, $start, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $startOffset = (int) $start[0][1];
        $slice = substr($text, $startOffset);
        if (preg_match($endPattern, $slice, $end, PREG_OFFSET_CAPTURE) === 1 && (int) $end[0][1] > 0) {
            return substr($slice, 0, (int) $end[0][1]);
        }

        return $slice;
    }

    /** @return array<string,mixed> */
    private function candidate(string $value, ?string $unit, int $page, string $excerpt, string $alias, float $confidence): array
    {
        return [
            'raw_value' => trim($value),
            'raw_unit' => $unit,
            'page' => $page,
            'excerpt' => str(trim($excerpt))->limit(240)->toString(),
            'alias' => $alias,
            'base_confidence' => $confidence,
            'warnings' => [],
        ];
    }

    private function variantKey(float $power): string
    {
        return rtrim(rtrim(number_format($power, 3, '.', ''), '0'), '.');
    }

    private function formatPower(float $power): string
    {
        return rtrim(rtrim(number_format($power, 2, '.', ''), '0'), '.');
    }

    /** @return array<string,mixed> */
    private function emptyResult(): array
    {
        return [
            'detected' => false,
            'blocked' => false,
            'selection_required' => false,
            'available_variants' => [],
            'selected_variant' => null,
            'selection_source' => null,
            'fields' => [],
            'warnings' => [],
        ];
    }
}
