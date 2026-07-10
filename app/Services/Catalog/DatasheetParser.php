<?php

namespace App\Services\Catalog;

use App\Models\Material;
use App\Support\MaterialCatalogStandards;
use Illuminate\Http\UploadedFile;

class DatasheetParser
{
    /**
     * Parse a datasheet upload with a deterministic text-based extractor.
     *
     * This intentionally avoids external services. PDF text extraction is kept
     * conservative for the foundation sprint. It works well for text-based or
     * test-uploaded datasheets and reports low confidence for scanned PDFs.
     *
     * @return array{status: string, text: string, fields: array<string, array<string, mixed>>, confidence: float, warnings: array<int, string>, parser_version: string}
     */
    public function parseUploadedFile(UploadedFile $file, ?Material $material = null): array
    {
        $raw = (string) @file_get_contents($file->getRealPath());
        $text = $this->normaliseRawText($raw);

        return $this->parseText($text, $material);
    }

    /**
     * @return array{status: string, text: string, fields: array<string, array<string, mixed>>, confidence: float, warnings: array<int, string>, parser_version: string}
     */
    public function parseText(string $text, ?Material $material = null): array
    {
        $cleanText = $this->normaliseRawText($text);
        $fields = [];
        $warnings = [];

        foreach (MaterialCatalogStandards::parserPatterns() as $key => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $cleanText, $matches) === 1) {
                    $value = trim((string) ($matches[1] ?? ''));

                    if ($value === '') {
                        continue;
                    }

                    $isNumeric = preg_match('/^-?\d+(?:[\.,]\d+)?$/', $value) === 1;

                    $fields[$key] = [
                        'label' => MaterialCatalogStandards::labelFor($key),
                        'value' => $isNumeric ? MaterialCatalogStandards::normalizeNumber($value) : trim($value),
                        'unit' => MaterialCatalogStandards::unitFor($key),
                        'confidence' => 0.86,
                        'source' => 'datasheet_text_pattern',
                    ];

                    break;
                }
            }
        }

        $requiredKeys = $material
            ? MaterialCatalogStandards::requiredSpecKeysFor($material)
            : [];

        $missingRequired = $requiredKeys === []
            ? []
            : array_values(array_diff($requiredKeys, array_keys($fields)));

        if ($cleanText === '') {
            $warnings[] = 'No readable text was extracted. The file may be a scanned PDF or image-only datasheet.';
        }

        if ($requiredKeys !== [] && $missingRequired !== []) {
            $warnings[] = 'Parser could not identify required fields: '.implode(', ', $missingRequired).'.';
        }

        $confidence = $requiredKeys === []
            ? (count($fields) > 0 ? 65.0 : 0.0)
            : round((count(array_intersect($requiredKeys, array_keys($fields))) / max(count($requiredKeys), 1)) * 100, 2);

        return [
            'status' => count($fields) > 0 ? 'parsed' : 'needs_manual_review',
            'text' => str($cleanText)->limit(50000, '')->toString(),
            'fields' => $fields,
            'confidence' => $confidence,
            'warnings' => $warnings,
            'parser_version' => MaterialCatalogStandards::PARSER_VERSION,
        ];
    }

    private function normaliseRawText(string $raw): string
    {
        $text = str_replace(["\0", "\r"], ['', "\n"], $raw);
        $text = preg_replace('/[^\x09\x0A\x20-\x7E]+/', ' ', $text) ?? '';
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

        return trim($text);
    }
}
