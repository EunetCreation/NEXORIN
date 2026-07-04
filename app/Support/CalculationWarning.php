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
            return self::make(
                (string) ($warning['code'] ?? $code),
                (string) ($warning['severity'] ?? 'warning'),
                (string) ($warning['title'] ?? self::titleFromCode((string) ($warning['code'] ?? $code))),
                (string) ($warning['message'] ?? $warning['text'] ?? ''),
                (string) ($warning['recommendation'] ?? 'Review this warning before using the result.'),
                (string) ($warning['source'] ?? $source)
            );
        }

        $message = trim((string) $warning);

        return self::make(
            $code,
            'warning',
            self::titleFromCode($code),
            $message,
            'Review this warning before using the result.',
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

    public static function titleFromCode(string $code): string
    {
        $title = strtolower(str_replace(['_', '-'], ' ', $code));

        return ucwords($title);
    }
}
