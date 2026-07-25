<?php

namespace App\Services\Catalog;

use App\Models\Material;
use App\Services\Catalog\Datasheet\DatasheetCategoryDetector;
use App\Services\Catalog\Datasheet\DatasheetDocumentExtractor;
use App\Services\Catalog\Datasheet\DatasheetFieldAliasRegistry;
use App\Services\Catalog\Datasheet\DatasheetUnitNormalizer;
use App\Services\Catalog\Datasheet\PvVariantTableParser;
use App\Support\ProfessionalSpecificationSchema;
use Illuminate\Http\UploadedFile;

class DatasheetParser
{
    public const VERSION = '64C-exact-category-ux-v1';

    public function __construct(
        private readonly DatasheetDocumentExtractor $extractor,
        private readonly DatasheetFieldAliasRegistry $aliases,
        private readonly DatasheetUnitNormalizer $unitNormalizer,
        private readonly DatasheetCategoryDetector $categoryDetector,
        private readonly PvVariantTableParser $pvVariantTableParser,
    ) {
    }

    /**
     * @return array{status:string,text:string,fields:array<string,array<string,mixed>>,confidence:float,warnings:array<int,string>,parser_version:string,pages:array<int,array{page:int,text:string}>,page_count:int,extraction_method:string,extraction_meta:array<string,mixed>}
     */
    public function parseUploadedFile(UploadedFile $file, Material $material, array $context = []): array
    {
        $document = $this->extractor->extract($file);

        return $this->parsePages($document['pages'], $material, [
            'method' => $document['method'],
            'page_count' => $document['page_count'],
            'scan_suspected' => $document['scan_suspected'],
            'warnings' => $document['warnings'],
            'text' => $document['text'],
        ], $context);
    }

    /**
     * Backwards-compatible deterministic text entry point used by tests and controlled imports.
     *
     * @return array<string,mixed>
     */
    public function parseText(string $text, ?Material $material = null, array $context = []): array
    {
        if (! $material) {
            return [
                'status' => 'needs_manual_review',
                'text' => trim($text),
                'fields' => [],
                'confidence' => 0.0,
                'warnings' => ['An exact material category is required for schema-aware parsing.'],
                'parser_version' => $this->version(),
                'pages' => [['page' => 1, 'text' => trim($text)]],
                'page_count' => 1,
                'extraction_method' => 'plain_text',
                'extraction_meta' => [],
            ];
        }

        return $this->parsePages([['page' => 1, 'text' => trim($text)]], $material, [
            'method' => 'plain_text',
            'page_count' => 1,
            'scan_suspected' => trim($text) === '',
            'warnings' => [],
            'text' => trim($text),
        ], $context);
    }

    /**
     * @param array<int,array{page:int,text:string}> $pages
     * @param array<string,mixed> $document
     * @return array<string,mixed>
     */
    private function parsePages(array $pages, Material $material, array $document, array $context = []): array
    {
        $schema = ProfessionalSpecificationSchema::for($material);
        $fieldMap = collect($schema['groups'])
            ->flatMap(fn (array $group): array => $group['fields'])
            ->keyBy('key');
        $existing = ($material->relationLoaded('specs') ? $material->getRelation('specs') : $material->specs()->get())->keyBy('spec_key');
        $fullText = (string) ($document['text'] ?? collect($pages)->pluck('text')->implode("\n"));
        $category = $this->categoryDetector->detect($fullText, $material, $context);
        $variant = $this->pvVariantTableParser->parse($pages, $material, $context);
        $governedCandidates = (array) ($variant['fields'] ?? []);
        $fields = [];
        $warnings = array_merge(
            (array) ($document['warnings'] ?? []),
            (array) ($variant['warnings'] ?? [])
        );

        $variantSpecificKeys = ['module_power_wp', 'vmp', 'imp', 'voc', 'isc', 'module_efficiency_percent'];
        foreach ($fieldMap as $key => $definition) {
            if ((bool) ($variant['detected'] ?? false)
                && ($variant['selected_variant'] ?? null) === null
                && in_array((string) $key, $variantSpecificKeys, true)) {
                continue;
            }

            $candidate = $this->findBestCandidate(
                (string) $key,
                (array) $definition,
                $pages,
                $governedCandidates[(string) $key] ?? null
            );
            if ($candidate === null) {
                continue;
            }

            $normalized = $this->unitNormalizer->normalize(
                (string) $candidate['raw_value'],
                $candidate['raw_unit'] !== null ? (string) $candidate['raw_unit'] : null,
                (array) $definition
            );
            if ($normalized['value'] === '') {
                continue;
            }

            $confidence = max(0.0, min(1.0, (float) $candidate['base_confidence'] + (float) $normalized['confidence_adjustment']));
            $existingSpec = $existing->get($key);
            $comparison = $this->compareExisting(
                $existingSpec?->spec_value,
                $existingSpec?->spec_unit,
                $normalized['value'],
                $normalized['unit']
            );
            $status = ! $normalized['valid'] || $confidence < 0.68
                ? 'low_confidence'
                : $comparison;

            $fields[$key] = [
                'label' => $definition['label'],
                'value' => $normalized['value'],
                'unit' => $normalized['unit'],
                'raw_value' => $normalized['raw_value'],
                'raw_unit' => $normalized['raw_unit'],
                'confidence' => round($confidence * 100, 2),
                'source' => 'schema_alias_parser',
                'source_page' => $candidate['page'],
                'source_excerpt' => $candidate['excerpt'],
                'matched_alias' => $candidate['alias'],
                'status' => $status,
                'existing_value' => $existingSpec?->spec_value,
                'existing_unit' => $existingSpec?->spec_unit,
                'validation_warnings' => array_values(array_unique(array_merge(
                    $normalized['warnings'],
                    $candidate['warnings'] ?? []
                ))),
                'alternative_candidates' => $candidate['alternatives'] ?? [],
                'suggested_action' => match ($status) {
                    'new' => 'accept',
                    'match' => 'keep',
                    default => 'keep',
                },
            ];
        }

        $required = (array) ($schema['required_specs'] ?? []);
        $missing = array_values(array_diff($required, array_keys($fields)));
        if ($missing !== []) {
            $labels = collect($missing)->map(fn (string $key): string => (string) ($fieldMap->get($key)['label'] ?? $key))->take(12)->implode(', ');
            $warnings[] = 'Required fields not confidently identified: '.$labels.(count($missing) > 12 ? ' and more.' : '.');
        }
        if ($category['blocked']) {
            $warnings[] = ($category['review_reason'] ?? null) === 'exact_component_mismatch'
                ? 'Exact component mismatch detected. Applying parsed values is blocked until the material category or source document is corrected.'
                : 'Document category mismatch detected. Applying parsed values is blocked until the correct material or document is selected.';
        } elseif (($category['match'] ?? 'uncertain') === 'uncertain') {
            $warnings[] = 'Document root category could not be confirmed with high confidence. No conflicting category was claimed, but the source still requires review.';
        } elseif (($category['exact_match'] ?? 'not_applicable') === 'uncertain') {
            $warnings[] = 'The professional root category is supported, but the exact component subtype could not be confirmed from the document wording.';
        }

        $fieldConfidence = collect($fields)->avg('confidence') ?? 0.0;
        $requiredCoverage = $required === [] ? 0.0 : (count(array_intersect($required, array_keys($fields))) / count($required)) * 100;
        $overall = round(($fieldConfidence * 0.65) + ($requiredCoverage * 0.35), 2);
        if ($category['blocked']) {
            $overall = min($overall, 35.0);
        } elseif ((bool) ($variant['blocked'] ?? false)) {
            $overall = min($overall, 55.0);
        }
        $status = $category['blocked']
            ? 'category_mismatch'
            : ((bool) ($variant['blocked'] ?? false)
                ? 'variant_selection_required'
                : ($fields === [] ? 'needs_manual_review' : 'parsed'));

        return [
            'status' => $status,
            'text' => str($fullText)->limit((int) config('nexorin_catalog.datasheet_parser.maximum_text_length', 200000), '')->toString(),
            'fields' => $fields,
            'confidence' => $overall,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'parser_version' => $this->version(),
            'pages' => $pages,
            'page_count' => (int) ($document['page_count'] ?? count($pages)),
            'extraction_method' => (string) ($document['method'] ?? 'plain_text'),
            'extraction_meta' => [
                'category' => $category,
                'scan_suspected' => (bool) ($document['scan_suspected'] ?? false),
                'extraction' => [
                    'method' => (string) ($document['method'] ?? 'plain_text'),
                    'page_count' => (int) ($document['page_count'] ?? count($pages)),
                    'degraded' => (string) ($document['method'] ?? '') === 'native_pdf_fallback',
                ],
                'schema_code' => $schema['code'],
                'required_field_count' => count($required),
                'identified_required_count' => count(array_intersect($required, array_keys($fields))),
                'variant' => [
                    'detected' => (bool) ($variant['detected'] ?? false),
                    'blocked' => (bool) ($variant['blocked'] ?? false),
                    'selection_required' => (bool) ($variant['selection_required'] ?? false),
                    'available_variants' => array_values((array) ($variant['available_variants'] ?? [])),
                    'selected_variant' => $variant['selected_variant'] ?? null,
                    'selection_source' => $variant['selection_source'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<int,array{page:int,text:string}> $pages
     * @return array{raw_value:string,raw_unit:?string,page:int,excerpt:string,alias:string,base_confidence:float}|null
     */
    private function findBestCandidate(string $key, array $definition, array $pages, ?array $governedCandidate = null): ?array
    {
        $candidates = [];
        if ($governedCandidate !== null) {
            $candidates[] = $governedCandidate;
        }
        $rangeCandidate = $this->findGovernedRangeCandidate($key, $pages);
        if ($rangeCandidate !== null) {
            $candidates[] = $rangeCandidate;
        }

        foreach ($this->aliases->aliases($key, $definition) as $alias) {
            foreach ($pages as $page) {
                $lines = preg_split('/\n+/', (string) $page['text']) ?: [];
                foreach ($lines as $index => $line) {
                    $aliasPattern = str_replace('\ ', '[\s\-_]*', preg_quote($alias, '/'));
                    $aliasPattern = '(?<![a-z0-9])'.$aliasPattern.'(?![a-z0-9])';
                    if (preg_match('/'.$aliasPattern.'/i', $line, $aliasMatch, PREG_OFFSET_CAPTURE) !== 1) {
                        continue;
                    }
                    $matchedText = (string) $aliasMatch[0][0];
                    $matchedOffset = (int) $aliasMatch[0][1];
                    $tail = trim(substr($line, $matchedOffset + strlen($matchedText)));
                    $candidate = $this->extractCandidate($tail, $definition);
                    $usedNextLine = false;
                    if ($candidate === null && isset($lines[$index + 1])) {
                        $candidate = $this->extractCandidate((string) $lines[$index + 1], $definition);
                        $usedNextLine = $candidate !== null;
                    }
                    if ($candidate === null) {
                        continue;
                    }

                    $base = $alias === $this->aliases->normalise((string) ($definition['label'] ?? '')) ? 0.88 : 0.82;
                    if (strlen($alias) <= 3) {
                        $base -= 0.06;
                    }
                    if ($usedNextLine) {
                        $base -= 0.08;
                    }
                    if (($candidate['ambiguous'] ?? false) === true) {
                        $base -= 0.30;
                    }
                    $candidates[] = [
                        'raw_value' => $candidate['value'],
                        'raw_unit' => $candidate['unit'],
                        'page' => (int) $page['page'],
                        'excerpt' => str(trim($line.($usedNextLine ? ' '.$lines[$index + 1] : '')))->limit(240)->toString(),
                        'alias' => $alias,
                        'base_confidence' => $base,
                        'warnings' => ($candidate['ambiguous'] ?? false)
                            ? ['Multiple values were found near this label. Confirm the correct model column before applying.']
                            : [],
                    ];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b): int => $b['base_confidence'] <=> $a['base_confidence']);
        $best = $candidates[0];
        $comparableCandidates = collect($candidates)
            ->filter(fn (array $candidate): bool => $candidate['base_confidence'] >= ($best['base_confidence'] - 0.05));
        $alternatives = $comparableCandidates
            ->map(fn (array $candidate): string => trim($candidate['raw_value'].' '.($candidate['raw_unit'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (count($alternatives) > 1) {
            $best['base_confidence'] -= 0.25;
            $best['warnings'][] = 'The document contains conflicting candidates for this field: '.implode(', ', array_slice($alternatives, 0, 5)).'.';
            $best['alternatives'] = $alternatives;
        }

        return $best;
    }


    /**
     * Parse governed two-endpoint ranges that map to separate normalized keys.
     *
     * @param array<int,array{page:int,text:string}> $pages
     * @return array{raw_value:string,raw_unit:?string,page:int,excerpt:string,alias:string,base_confidence:float,warnings:array<int,string>}|null
     */
    private function findGovernedRangeCandidate(string $key, array $pages): ?array
    {
        if (! in_array($key, ['mppt_voltage_min', 'mppt_voltage_max'], true)) {
            return null;
        }

        $aliases = ['mppt voltage range', 'mpp voltage range', 'mppt operating voltage range', 'mppt range'];
        foreach ($pages as $page) {
            foreach (preg_split('/\n+/', (string) $page['text']) ?: [] as $line) {
                foreach ($aliases as $alias) {
                    $aliasPattern = str_replace('\ ', '[\s\-_]*', preg_quote($alias, '/'));
                    if (preg_match('/(?<![a-z0-9])'.$aliasPattern.'(?![a-z0-9])/i', $line, $match, PREG_OFFSET_CAPTURE) !== 1) {
                        continue;
                    }

                    $tail = trim(substr($line, (int) $match[0][1] + strlen((string) $match[0][0])));
                    $tail = trim(preg_replace('/^[\s:=–—]+/', '', $tail) ?? $tail);
                    if (preg_match('/([-+]?\d[\d.,]*)\s*(?:to|[-–—~…])\s*([-+]?\d[\d.,]*)\s*([%°\/a-zA-Z²³Ω]+)?/iu', $tail, $range) !== 1) {
                        continue;
                    }

                    return [
                        'raw_value' => $key === 'mppt_voltage_min' ? $range[1] : $range[2],
                        'raw_unit' => isset($range[3]) && $range[3] !== '' ? trim($range[3]) : 'V',
                        'page' => (int) $page['page'],
                        'excerpt' => str(trim($line))->limit(240)->toString(),
                        'alias' => $alias,
                        'base_confidence' => 0.92,
                        'warnings' => [],
                    ];
                }
            }
        }

        return null;
    }

    /** @param array<string,mixed> $definition @return array{value:string,unit:?string,ambiguous?:bool}|null */
    private function extractCandidate(string $tail, array $definition): ?array
    {
        $tail = trim(preg_replace('/^[\s:=–—]+/', '', $tail) ?? $tail);
        if ($tail === '') {
            return null;
        }

        $type = (string) ($definition['input_type'] ?? 'text');
        if ($type === 'number') {
            if (preg_match_all('/([-+]?\d[\d.,]*)(?:\s*([%°\/a-zA-Z²³Ω\-]+))?/', $tail, $matches, PREG_SET_ORDER) < 1) {
                return null;
            }

            $first = $matches[0];
            return [
                'value' => $first[1],
                'unit' => isset($first[2]) ? trim($first[2]) : null,
                'ambiguous' => count($matches) > 1,
            ];
        }

        $value = trim(preg_split('/\s{3,}|\|/', $tail)[0] ?? $tail);
        $value = trim($value, ":;,. \t\n\r\0\x0B");
        if ($value === '') {
            return null;
        }

        return ['value' => str($value)->limit(180, '')->toString(), 'unit' => null];
    }

    private function compareExisting(?string $existingValue, ?string $existingUnit, string $value, ?string $unit): string
    {
        if ($existingValue === null || trim($existingValue) === '') {
            return 'new';
        }

        if (is_numeric($existingValue) && is_numeric($value)) {
            $a = (float) $existingValue;
            $b = (float) $value;
            $tolerance = max(0.000001, abs($a) * 0.005);

            return abs($a - $b) <= $tolerance && strtolower((string) $existingUnit) === strtolower((string) $unit)
                ? 'match'
                : 'conflict';
        }

        return strtolower(trim($existingValue)) === strtolower(trim($value))
            ? 'match'
            : 'conflict';
    }

    private function version(): string
    {
        return self::VERSION;
    }
}
