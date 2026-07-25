<?php

namespace App\Services\Catalog\Datasheet;

class DatasheetUnitNormalizer
{
    /**
     * @param array<string,mixed> $definition
     * @return array{value:string,unit:?string,raw_value:string,raw_unit:?string,valid:bool,warnings:array<int,string>,confidence_adjustment:float}
     */
    public function normalize(string $rawValue, ?string $rawUnit, array $definition): array
    {
        $inputType = (string) ($definition['input_type'] ?? 'text');
        $targetUnit = $definition['unit'] ?? null;
        $warnings = [];
        $adjustment = 0.0;

        if ($inputType === 'select') {
            $normalized = $this->normalizeSelect($rawValue, (array) ($definition['options'] ?? []));
            if ($normalized === null) {
                return $this->result(trim($rawValue), $targetUnit, $rawValue, $rawUnit, false, ['Value does not match the controlled options for this field.'], -0.25);
            }

            return $this->result($normalized, $targetUnit, $rawValue, $rawUnit, true, [], 0.04);
        }

        if ($inputType !== 'number') {
            $value = trim(preg_replace('/\s+/', ' ', $rawValue) ?? $rawValue);
            if (is_string($targetUnit) && $targetUnit !== '') {
                $unitTokens = match ($this->normalizeUnit($targetUnit)) {
                    'v' => ['v', 'vac', 'vdc', 'volt', 'volts'],
                    'a' => ['a', 'amp', 'amps', 'ampere'],
                    'mm' => ['mm', 'millimeter', 'millimeters'],
                    'm' => ['m', 'meter', 'meters'],
                    'c' => ['c', '°c', 'degc'],
                    default => [strtolower($targetUnit)],
                };
                $pattern = '/\s*(?:'.implode('|', array_map(fn (string $token): string => preg_quote($token, '/'), $unitTokens)).')\s*$/i';
                $value = trim(preg_replace($pattern, '', $value) ?? $value);
            }

            return $this->result($value, $targetUnit, $rawValue, $rawUnit, $value !== '', [], 0.0);
        }

        $numberCandidates = $this->parseNumberCandidates($rawValue);
        if ($numberCandidates === []) {
            return $this->result(trim($rawValue), $targetUnit, $rawValue, $rawUnit, false, ['Numeric value could not be normalized.'], -0.35);
        }

        $sourceUnit = $this->normalizeUnit($rawUnit);
        $target = $this->normalizeUnit(is_string($targetUnit) ? $targetUnit : null);
        $conversionFactor = 1.0;
        $unitCompatible = true;

        if ($sourceUnit !== null && $target !== null && $sourceUnit !== $target) {
            $conversion = $this->conversion($sourceUnit, $target);
            if ($conversion === null) {
                $warnings[] = "Unit {$rawUnit} is not compatible with {$targetUnit}. Correct the candidate unit before applying it.";
                $adjustment -= 0.25;
                $unitCompatible = false;
            } else {
                $conversionFactor = $conversion;
                $adjustment += 0.04;
            }
        } elseif ($target !== null && $sourceUnit === null) {
            $warnings[] = "No source unit was detected; {$targetUnit} was inferred from the selected component schema.";
            $adjustment -= 0.08;
        } elseif ($target !== null && $sourceUnit === $target) {
            $adjustment += 0.05;
        }

        if (count($numberCandidates) > 1 && $conversionFactor > 0 && $conversionFactor < 1) {
            // A grouped source value such as 5.120 Wh should be considered before
            // the decimal interpretation when normalizing into a larger unit.
            $numberCandidates = array_reverse($numberCandidates);
        }

        $convertedCandidates = collect($numberCandidates)
            ->map(fn (float $candidate): float => $candidate * $conversionFactor)
            ->unique(fn (float $candidate): string => sprintf('%.12F', $candidate))
            ->values();
        $withinGovernedRange = $convertedCandidates->filter(function (float $candidate) use ($definition): bool {
            if (isset($definition['min']) && $definition['min'] !== null && $candidate < (float) $definition['min']) {
                return false;
            }
            if (isset($definition['max']) && $definition['max'] !== null && $candidate > (float) $definition['max']) {
                return false;
            }

            return true;
        })->values();
        $converted = (float) ($withinGovernedRange->first() ?? $convertedCandidates->first());

        if ($convertedCandidates->count() > 1) {
            $warnings[] = $withinGovernedRange->count() === 1
                ? 'An ambiguous decimal or thousands separator was resolved using the governed field range.'
                : 'The numeric separator is ambiguous. Confirm the normalized value against the source document.';
            $adjustment -= $withinGovernedRange->count() === 1 ? 0.02 : 0.30;
        }

        $valid = $unitCompatible;
        if (isset($definition['min']) && $definition['min'] !== null && $converted < (float) $definition['min']) {
            $valid = false;
            $warnings[] = 'Value is below the governed minimum for this field.';
        }
        if (isset($definition['max']) && $definition['max'] !== null && $converted > (float) $definition['max']) {
            $valid = false;
            $warnings[] = 'Value is above the governed maximum for this field.';
        }

        $step = (string) ($definition['step'] ?? '0.01');
        $value = $step === '1'
            ? (string) (int) round($converted)
            : rtrim(rtrim(number_format($converted, 6, '.', ''), '0'), '.');

        return $this->result($value, $targetUnit, $rawValue, $rawUnit, $valid, $warnings, $adjustment + ($valid ? 0.03 : -0.25));
    }

    /** @return array<int,float> */
    private function parseNumberCandidates(string $raw): array
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $raw));
        $value = preg_replace('/[^0-9,\.\-+]/', '', $value) ?? '';
        if ($value === '' || in_array($value, ['-', '+', '.', ','], true)) {
            return [];
        }

        $sign = 1.0;
        if (str_starts_with($value, '-')) {
            $sign = -1.0;
        }
        $unsigned = ltrim($value, '+-');
        $candidates = [];
        $commaCount = substr_count($unsigned, ',');
        $dotCount = substr_count($unsigned, '.');

        if ($commaCount > 0 && $dotCount > 0) {
            $lastComma = strrpos($unsigned, ',');
            $lastDot = strrpos($unsigned, '.');
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $normalized = str_replace($thousands, '', $unsigned);
            $normalized = str_replace($decimal, '.', $normalized);
            if (is_numeric($normalized)) {
                $candidates[] = $sign * (float) $normalized;
            }
        } elseif ($commaCount === 1 || $dotCount === 1) {
            $separator = $commaCount === 1 ? ',' : '.';
            [$integerPart, $fractionPart] = array_pad(explode($separator, $unsigned, 2), 2, '');
            $decimal = $integerPart.'.'.$fractionPart;
            $looksGrouped = strlen($fractionPart) === 3 && $integerPart !== '' && $integerPart !== '0';

            if ($separator === ',' && $looksGrouped) {
                $candidates[] = $sign * (float) ($integerPart.$fractionPart);
                $candidates[] = $sign * (float) $decimal;
            } else {
                $candidates[] = $sign * (float) $decimal;
                if ($looksGrouped) {
                    $candidates[] = $sign * (float) ($integerPart.$fractionPart);
                }
            }
        } elseif ($commaCount > 1 || $dotCount > 1) {
            $separator = $commaCount > 1 ? ',' : '.';
            $parts = explode($separator, $unsigned);
            $allGrouped = count($parts) > 1
                && collect(array_slice($parts, 1))->every(fn (string $part): bool => strlen($part) === 3);
            if ($allGrouped) {
                $candidates[] = $sign * (float) implode('', $parts);
            } else {
                $last = array_pop($parts);
                $normalized = implode('', $parts).'.'.$last;
                if (is_numeric($normalized)) {
                    $candidates[] = $sign * (float) $normalized;
                }
            }
        } elseif (is_numeric($unsigned)) {
            $candidates[] = $sign * (float) $unsigned;
        }

        return collect($candidates)
            ->filter(fn (float $candidate): bool => is_finite($candidate))
            ->unique(fn (float $candidate): string => sprintf('%.12F', $candidate))
            ->values()
            ->all();
    }

    /** @param array<string,string> $options */
    private function normalizeSelect(string $value, array $options): ?string
    {
        $needle = strtolower(trim($value));
        if ($needle === '') {
            return null;
        }

        $booleanMap = ['yes' => '1', 'true' => '1', 'supported' => '1', 'included' => '1', 'no' => '0', 'false' => '0', 'not supported' => '0', 'not included' => '0'];
        if (isset($booleanMap[$needle]) && array_key_exists($booleanMap[$needle], $options)) {
            return $booleanMap[$needle];
        }

        foreach ($options as $key => $label) {
            if ($needle === strtolower((string) $key) || $needle === strtolower((string) $label)) {
                return (string) $key;
            }
            if (str_contains(strtolower((string) $label), $needle) || str_contains($needle, strtolower((string) $label))) {
                return (string) $key;
            }
        }

        return null;
    }

    private function normalizeUnit(?string $unit): ?string
    {
        if ($unit === null || trim($unit) === '') {
            return null;
        }

        $value = strtolower(trim($unit));
        $value = str_replace([' ', '°', '²', '³'], ['', '', '2', '3'], $value);
        $map = [
            'watt' => 'w', 'watts' => 'w', 'wp' => 'wp', 'kwp' => 'kwp', 'kw' => 'kw', 'mw' => 'mw',
            'wh' => 'wh', 'kwh' => 'kwh', 'mwh' => 'mwh',
            'volt' => 'v', 'volts' => 'v', 'vdc' => 'v', 'vac' => 'v', 'mv' => 'mv', 'kv' => 'kv',
            'amp' => 'a', 'amps' => 'a', 'ampere' => 'a', 'amperes' => 'a', 'ma' => 'ma', 'ka' => 'ka',
            'millimeter' => 'mm', 'millimeters' => 'mm', 'cm' => 'cm', 'meter' => 'm', 'meters' => 'm',
            'gram' => 'g', 'grams' => 'g', 'kilogram' => 'kg', 'kilograms' => 'kg',
            'percent' => '%', 'percentage' => '%',
            'second' => 's', 'seconds' => 's', 'millisecond' => 'ms', 'milliseconds' => 'ms', 'nanosecond' => 'ns', 'nanoseconds' => 'ns',
            'year' => 'years', 'yr' => 'years', 'yrs' => 'years', 'tahun' => 'years',
            'ohms' => 'ohm', 'ω' => 'ohm',
            'pa' => 'pa', 'kpa' => 'kpa', 'mpa' => 'mpa',
        ];

        return $map[$value] ?? $value;
    }

    private function conversion(string $from, string $to): ?float
    {
        $dimensions = [
            'power' => ['w' => 1, 'wp' => 1, 'kw' => 1000, 'kwp' => 1000, 'mw' => 1000000],
            'energy' => ['wh' => 1, 'kwh' => 1000, 'mwh' => 1000000],
            'voltage' => ['mv' => 0.001, 'v' => 1, 'kv' => 1000],
            'current' => ['ma' => 0.001, 'a' => 1, 'ka' => 1000],
            'length' => ['mm' => 0.001, 'cm' => 0.01, 'm' => 1],
            'mass' => ['g' => 0.001, 'kg' => 1],
            'time' => ['ns' => 0.000000001, 'ms' => 0.001, 's' => 1],
            'pressure' => ['pa' => 1, 'kpa' => 1000, 'mpa' => 1000000],
        ];

        foreach ($dimensions as $units) {
            if (isset($units[$from], $units[$to])) {
                return $units[$from] / $units[$to];
            }
        }

        return null;
    }

    /** @return array{value:string,unit:?string,raw_value:string,raw_unit:?string,valid:bool,warnings:array<int,string>,confidence_adjustment:float} */
    private function result(string $value, ?string $unit, string $rawValue, ?string $rawUnit, bool $valid, array $warnings, float $adjustment): array
    {
        return [
            'value' => $value,
            'unit' => $unit,
            'raw_value' => $rawValue,
            'raw_unit' => $rawUnit,
            'valid' => $valid,
            'warnings' => $warnings,
            'confidence_adjustment' => $adjustment,
        ];
    }
}
