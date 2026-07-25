<?php

namespace App\Services\Catalog\Datasheet;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DatasheetDocumentExtractor
{
    public function __construct(private readonly NativePdfTextExtractor $nativePdfTextExtractor)
    {
    }

    /**
     * @return array{text:string,pages:array<int,array{page:int,text:string}>,page_count:int,method:string,warnings:array<int,string>,scan_suspected:bool}
     */
    public function extract(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $raw = (string) file_get_contents($file->getRealPath());

        if (in_array($extension, ['txt', 'csv'], true)) {
            $text = $this->normalise($raw);

            return $this->result([['page' => 1, 'text' => $text]], 'plain_text', [], $text === '');
        }

        if ($extension !== 'pdf') {
            return $this->result([], 'unsupported', ['The selected file type is not supported by the datasheet parser.'], true);
        }

        $external = $this->extractWithPdftotext($file->getRealPath());
        if ($external !== null && trim($external['text']) !== '') {
            $pages = $this->splitPages($external['text']);

            return $this->result($pages, 'pdftotext', $external['warnings'], false);
        }

        $native = $this->nativePdfTextExtractor->extract($raw);
        $pages = $native['pages'];
        $textLength = strlen(implode(' ', array_column($pages, 'text')));
        $scanSuspected = $textLength < 80;
        $warnings = array_merge($external['warnings'] ?? [], $native['warnings']);
        if ($scanSuspected) {
            $warnings[] = 'Very little readable text was recovered. This is likely an image-only or scanned datasheet and requires OCR or manual review.';
        }

        return $this->result($pages, 'native_pdf_fallback', array_values(array_unique($warnings)), $scanSuspected);
    }

    /** @return array{text:string,warnings:array<int,string>}|null */
    private function extractWithPdftotext(string $path): ?array
    {
        $configured = (string) config('nexorin_catalog.datasheet_parser.pdftotext_binary', 'pdftotext');
        $binary = $this->resolveBinary($configured);
        if ($binary === null) {
            return ['text' => '', 'warnings' => ['The optional pdftotext utility is unavailable. The native PDF fallback was used.']];
        }

        try {
            $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $path, '-']);
            $process->setTimeout((float) config('nexorin_catalog.datasheet_parser.process_timeout_seconds', 20));
            $process->run();

            if (! $process->isSuccessful()) {
                return ['text' => '', 'warnings' => ['pdftotext could not extract the document. The native PDF fallback was used.']];
            }

            return ['text' => $this->normalisePageBreaks($process->getOutput()), 'warnings' => []];
        } catch (Throwable) {
            return ['text' => '', 'warnings' => ['PDF extraction utility execution failed. The native PDF fallback was used.']];
        }
    }

    private function resolveBinary(string $configured): ?string
    {
        if ($configured !== '' && is_file($configured) && is_executable($configured)) {
            return $configured;
        }

        return (new ExecutableFinder())->find($configured !== '' ? $configured : 'pdftotext');
    }

    /** @return array<int,array{page:int,text:string}> */
    private function splitPages(string $text): array
    {
        $chunks = preg_split('/\f/', $text) ?: [$text];
        $pages = [];
        foreach ($chunks as $index => $chunk) {
            $normalized = $this->normalise($chunk);
            if ($normalized === '' && count($chunks) > 1) {
                continue;
            }
            $pages[] = ['page' => $index + 1, 'text' => $normalized];
        }

        return $pages === [] ? [['page' => 1, 'text' => '']] : $pages;
    }

    private function normalisePageBreaks(string $text): string
    {
        $text = str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], $text);

        return preg_replace('/[ \t]+/', ' ', $text) ?? '';
    }

    private function normalise(string $text): string
    {
        $text = $this->normalisePageBreaks($text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

        return trim($text);
    }

    /**
     * @param array<int,array{page:int,text:string}> $pages
     * @param array<int,string> $warnings
     * @return array{text:string,pages:array<int,array{page:int,text:string}>,page_count:int,method:string,warnings:array<int,string>,scan_suspected:bool}
     */
    private function result(array $pages, string $method, array $warnings, bool $scanSuspected): array
    {
        $maxLength = (int) config('nexorin_catalog.datasheet_parser.maximum_text_length', 200000);
        $text = collect($pages)
            ->map(fn (array $page): string => "--- PAGE {$page['page']} ---\n".$page['text'])
            ->implode("\n\n");

        return [
            'text' => str($text)->limit($maxLength, '')->toString(),
            'pages' => $pages,
            'page_count' => count($pages),
            'method' => $method,
            'warnings' => $warnings,
            'scan_suspected' => $scanSuspected,
        ];
    }
}
