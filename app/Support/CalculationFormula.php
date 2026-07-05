<?php

namespace App\Support;

class CalculationFormula
{
    /**
     * @param  array<string, array{label:string,value:mixed,unit:string}>  $inputs
     * @return array{code:string,category:string,label:string,formula:string,display_formula:string,inputs:array<string, array{label:string,value:mixed,unit:string}>,result:mixed,unit:string,explanation:string,source:string}
     */
    public static function make(
        string $code,
        string $category,
        string $label,
        string $formula,
        string $displayFormula,
        array $inputs,
        mixed $result,
        string $unit,
        string $explanation,
        string $source = 'technical_calculation_engine'
    ): array {
        return [
            'code' => strtoupper(trim($code)),
            'category' => trim($category) ?: 'General',
            'label' => trim($label),
            'formula' => trim($formula),
            'display_formula' => trim($displayFormula),
            'inputs' => self::normalizeInputs($inputs),
            'result' => $result,
            'unit' => trim($unit),
            'explanation' => trim($explanation),
            'source' => trim($source) ?: 'technical_calculation_engine',
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, array{label:string,value:mixed,unit:string}>
     */
    private static function normalizeInputs(array $inputs): array
    {
        $normalized = [];

        foreach ($inputs as $key => $input) {
            if (is_array($input)) {
                $normalized[$key] = [
                    'label' => (string) ($input['label'] ?? self::labelFromKey((string) $key)),
                    'value' => $input['value'] ?? null,
                    'unit' => (string) ($input['unit'] ?? ''),
                ];

                continue;
            }

            $normalized[$key] = [
                'label' => self::labelFromKey((string) $key),
                'value' => $input,
                'unit' => '',
            ];
        }

        return $normalized;
    }

    public static function labelFromKey(string $key): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }

    /**
     * @param  array<string, mixed>  $formula
     */
    public static function text(array $formula): string
    {
        $label = (string) ($formula['label'] ?? 'Formula');
        $displayFormula = (string) ($formula['display_formula'] ?? $formula['formula'] ?? '-');
        $result = self::formatValue($formula['result'] ?? null, (string) ($formula['unit'] ?? ''));

        return trim(sprintf('%s: %s = %s', $label, $displayFormula, $result));
    }

    public static function formatValue(mixed $value, string $unit = ''): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_numeric($value)) {
            $formatted = rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');
        } else {
            $formatted = (string) $value;
        }

        return trim($formatted.' '.trim($unit));
    }
}
