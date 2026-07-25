<?php

namespace App\Services\Catalog\Datasheet;

class NativePdfTextExtractor
{
    /**
     * Conservative pure-PHP fallback for text-based PDF content streams.
     * It intentionally does not attempt OCR or encrypted PDF decryption.
     *
     * @return array{pages:array<int,array{page:int,text:string}>,warnings:array<int,string>}
     */
    public function extract(string $raw): array
    {
        $warnings = [];
        $chunks = [];

        if (! str_starts_with(ltrim($raw), '%PDF-')) {
            return [
                'pages' => [['page' => 1, 'text' => $this->normalise($raw)]],
                'warnings' => ['The uploaded file did not contain a valid PDF header. Plain readable text was used as a compatibility fallback.'],
            ];
        }

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($streams[1] ?? [] as [$stream, $offset]) {
                $dictionary = substr($raw, max(0, $offset - 800), 800);
                $decoded = $stream;

                if (str_contains($dictionary, '/FlateDecode')) {
                    $inflated = @gzuncompress($stream);
                    if ($inflated === false) {
                        $inflated = @gzinflate($stream);
                    }
                    if ($inflated === false && strlen($stream) > 2) {
                        $inflated = @gzinflate(substr($stream, 2));
                    }
                    if ($inflated === false) {
                        continue;
                    }
                    $decoded = $inflated;
                }

                $text = $this->extractTextOperators($decoded);
                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        $text = $this->normalise(implode("\n", $chunks));
        if ($text === '') {
            $warnings[] = 'The native PDF fallback could not recover readable text. The file may use embedded font encoding, encryption, or scanned images.';
        } else {
            $warnings[] = 'Text was extracted with the native PDF fallback. Verify page trace and values carefully because complex embedded font maps may reduce fidelity.';
        }

        return [
            'pages' => [['page' => 1, 'text' => $text]],
            'warnings' => $warnings,
        ];
    }

    private function extractTextOperators(string $stream): string
    {
        $fragments = [];

        if (preg_match_all('/\((?:\\.|[^\\)])*\)\s*Tj/s', $stream, $matches) !== false) {
            foreach ($matches[0] ?? [] as $match) {
                $literal = preg_replace('/\s*Tj\s*$/', '', $match) ?? '';
                $fragments[] = $this->decodeLiteralString($literal);
            }
        }

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $stream, $arrays) !== false) {
            foreach ($arrays[1] ?? [] as $array) {
                if (preg_match_all('/\((?:\\.|[^\\)])*\)|<([0-9A-Fa-f\s]+)>/', $array, $parts) !== false) {
                    $line = '';
                    foreach ($parts[0] ?? [] as $part) {
                        $line .= str_starts_with($part, '(')
                            ? $this->decodeLiteralString($part)
                            : $this->decodeHexString($part);
                    }
                    if ($line !== '') {
                        $fragments[] = $line;
                    }
                }
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/', $stream, $hexMatches) !== false) {
            foreach ($hexMatches[0] ?? [] as $match) {
                $fragments[] = $this->decodeHexString($match);
            }
        }

        return $this->normalise(implode("\n", $fragments));
    }

    private function decodeLiteralString(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $value = substr($value, 1, -1);
        }

        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static fn (array $match): string => chr(octdec($match[1])), $value) ?? $value;

        return strtr($value, [
            '\\n' => "\n",
            '\\r' => "\n",
            '\\t' => "\t",
            '\\b' => "\b",
            '\\f' => "\f",
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ]);
    }

    private function decodeHexString(string $value): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $value) ?? '';
        if ($hex === '') {
            return '';
        }
        if (strlen($hex) % 2 !== 0) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);
        if ($binary === false) {
            return '';
        }

        if (str_starts_with($binary, "\xFE\xFF") && function_exists('iconv')) {
            return (string) @iconv('UTF-16BE', 'UTF-8//IGNORE', substr($binary, 2));
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $binary) ?? '';
    }

    private function normalise(string $text): string
    {
        $text = str_replace(["\0", "\r"], ['', "\n"], $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

        return trim($text);
    }
}
