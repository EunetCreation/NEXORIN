<?php

namespace App\Support;

class CalculationWarning
{
    public const SEVERITIES = ['info', 'caution', 'warning', 'critical'];

    /**
     * @return array{code:string,severity:string,title:string,message:string,recommendation:string,source:string}
     */
    public static function make(
        string $code,
        string $severity,
        string $title,
        string $message,
        string $recommendation,
        string $source = 'technical_calculation'
    ): array {
        $severity = in_array($severity, self::SEVERITIES, true) ? $severity : 'info';

        return [
            'code' => strtoupper(trim($code)),
            'severity' => $severity,
            'title' => trim($title),
            'message' => trim($message),
            'recommendation' => trim($recommendation),
            'source' => trim($source) ?: 'technical_calculation',
        ];
    }

    /**
     * @return array{code:string,severity:string,title:string,message:string,recommendation:string,source:string}
     */
    public static function fromLegacy(mixed $warning, string $source = 'system', string $code = 'LEGACY_WARNING'): array
    {
        if (is_array($warning)) {
            $message = trim((string) ($warning['message'] ?? $warning['text'] ?? ''));
            $candidateCode = (string) ($warning['code'] ?? '');
            $recordCode = $candidateCode === '' || strtoupper($candidateCode) === 'LEGACY_WARNING'
                ? self::inferLegacyCode($message, $source, $code)
                : $candidateCode;
            $candidateTitle = trim((string) ($warning['title'] ?? ''));
            $recordTitle = $candidateTitle === '' || strtolower($candidateTitle) === 'legacy warning'
                ? self::professionalTitle($message, $recordCode, $source)
                : $candidateTitle;

            return self::make(
                $recordCode,
                (string) ($warning['severity'] ?? self::inferSeverity($message, $source)),
                $recordTitle,
                $message,
                (string) ($warning['recommendation'] ?? self::defaultRecommendation($source)),
                (string) ($warning['source'] ?? $source)
            );
        }

        $message = trim((string) $warning);
        $recordCode = self::inferLegacyCode($message, $source, $code);

        return self::make(
            $recordCode,
            self::inferSeverity($message, $source),
            self::professionalTitle($message, $recordCode, $source),
            $message,
            self::defaultRecommendation($source),
            $source
        );
    }

    /**
     * @param  array<int, mixed>  $warnings
     * @return array<int, array{code:string,severity:string,title:string,message:string,recommendation:string,source:string}>
     */
    public static function normalizeList(array $warnings, string $defaultSource = 'system'): array
    {
        $records = [];

        foreach ($warnings as $warning) {
            $records[] = self::fromLegacy($warning, $defaultSource);
        }

        return self::unique($records);
    }

    /**
     * @param  array<int, mixed>  $warnings
     * @return array<int, array{code:string,severity:string,title:string,message:string,recommendation:string,source:string}>
     */
    public static function unique(array $warnings): array
    {
        $unique = [];

        foreach ($warnings as $warning) {
            $record = self::fromLegacy($warning);
            $key = implode('|', [
                $record['code'],
                $record['severity'],
                $record['title'],
                $record['message'],
                $record['recommendation'],
                $record['source'],
            ]);

            $unique[$key] = $record;
        }

        return array_values($unique);
    }

    public static function text(mixed $warning): string
    {
        $record = self::fromLegacy($warning);
        $prefix = strtoupper($record['severity']);

        return sprintf('[%s] %s: %s Recommendation: %s',
            $prefix,
            $record['title'],
            $record['message'],
            $record['recommendation']
        );
    }

    public static function message(mixed $warning): string
    {
        return self::fromLegacy($warning)['message'];
    }

    public static function severity(mixed $warning): string
    {
        return self::fromLegacy($warning)['severity'];
    }

    public static function code(mixed $warning): string
    {
        return self::fromLegacy($warning)['code'];
    }

    public static function title(mixed $warning): string
    {
        return self::fromLegacy($warning)['title'];
    }

    public static function recommendation(mixed $warning): string
    {
        return self::fromLegacy($warning)['recommendation'];
    }


    private static function inferLegacyCode(string $message, string $source, string $fallbackCode): string
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'dc/ac ratio') || str_contains($normalized, 'dc ac ratio')) {
            return 'DC_AC_RATIO_LOW';
        }

        if (str_contains($normalized, 'voltage drop')) {
            return 'VOLTAGE_DROP_REVIEW';
        }

        if (str_contains($normalized, 'string') && (str_contains($normalized, 'voltage') || str_contains($normalized, 'mppt') || str_contains($normalized, 'incompatible'))) {
            return 'STRING_COMPATIBILITY_REVIEW';
        }

        if (str_contains($normalized, 'roof') || str_contains($normalized, 'usable area')) {
            return 'ROOF_CONSTRAINT_REVIEW';
        }

        if (str_contains($normalized, 'not been engineer verified') || str_contains($normalized, 'engineer verified')) {
            return 'ENGINEERING_VERIFICATION_REQUIRED';
        }

        if (str_contains($normalized, 'not priced') || str_contains($normalized, 'pricing')) {
            return 'PRICING_REQUIRED';
        }

        if ($fallbackCode !== 'LEGACY_WARNING') {
            return $fallbackCode;
        }

        return strtoupper($source).'_NOTICE';
    }

    private static function professionalTitle(string $message, string $code, string $source): string
    {
        $normalized = strtolower($message);

        if ($code === 'DC_AC_RATIO_LOW') {
            return 'Low DC/AC Ratio';
        }

        if ($code === 'VOLTAGE_DROP_REVIEW') {
            return 'Voltage Drop Review Required';
        }

        if ($code === 'STRING_COMPATIBILITY_REVIEW') {
            return 'String Compatibility Review';
        }

        if ($code === 'ROOF_CONSTRAINT_REVIEW') {
            return 'Roof Constraint Review';
        }

        if ($code === 'ENGINEERING_VERIFICATION_REQUIRED') {
            return 'Engineering Verification Required';
        }

        if ($code === 'PRICING_REQUIRED') {
            return 'Pricing Required';
        }

        if (str_contains($normalized, 'warning')) {
            return self::titleFromCode($code);
        }

        return match ($source) {
            'technical_calculation' => 'Technical Review Notice',
            'system_recommendation' => 'Recommendation Review Notice',
            'roof_site_survey' => 'Roof Survey Notice',
            'bom_boq' => 'BOM/BOQ Review Notice',
            'financial_analysis' => 'Financial Review Notice',
            'quotation' => 'Quotation Review Notice',
            default => self::titleFromCode($code),
        };
    }

    private static function inferSeverity(string $message, string $source): string
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'incompatible') || str_contains($normalized, 'exceeds') || str_contains($normalized, 'critical')) {
            return 'critical';
        }

        if (str_contains($normalized, 'not been engineer verified') || str_contains($normalized, 'not priced')) {
            return 'warning';
        }

        if (str_contains($normalized, 'low') || str_contains($normalized, 'review')) {
            return 'caution';
        }

        return $source === 'technical_override' ? 'info' : 'warning';
    }

    private static function defaultRecommendation(string $source): string
    {
        return match ($source) {
            'technical_calculation' => 'Review the calculation basis, assumptions, and override status before using this result.',
            'system_recommendation' => 'Review scoring inputs and recommendation constraints before selecting the final system.',
            'roof_site_survey' => 'Confirm site measurements, shading, access, and structural notes before procurement.',
            'bom_boq' => 'Review the BOM/BOQ status and confirm pricing before issuing commercial documents.',
            'financial_analysis' => 'Review CAPEX source, tariff assumptions, and cashflow assumptions before presenting results.',
            'quotation' => 'Review quotation totals, terms, validity, and exclusions before sending to the customer.',
            default => 'Review this notice before using the result.',
        };
    }

    public static function titleFromCode(string $code): string
    {
        $title = strtolower(str_replace(['_', '-'], ' ', $code));

        return ucwords($title);
    }
}
