<?php

namespace App\Services\Sld;

use Illuminate\Support\Str;

class SldExportPackageBuilder
{
    /**
     * Build a dependency-free ZIP archive using the STORE method.
     *
     * @param array<string, string> $files
     */
    public function build(array $files): string
    {
        $localData = '';
        $centralData = '';
        $offset = 0;
        $count = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();

        foreach ($files as $name => $contents) {
            $safeName = $this->safeName((string) $name);
            if ($safeName === '') {
                continue;
            }

            $contents = (string) $contents;
            $nameBytes = $safeName;
            $size = strlen($contents);
            $crc = (int) hexdec(hash('crc32b', $contents));
            $flags = 0x0800; // UTF-8 filename.

            $localHeader = pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                $flags,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0
            );
            $localRecord = $localHeader.$nameBytes.$contents;
            $localData .= $localRecord;

            $centralData .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0x0314,
                20,
                $flags,
                0,
                $dosTime,
                $dosDate,
                $crc,
                $size,
                $size,
                strlen($nameBytes),
                0,
                0,
                0,
                0,
                0,
                $offset
            ).$nameBytes;

            $offset += strlen($localRecord);
            $count++;
        }

        $centralOffset = strlen($localData);
        $centralSize = strlen($centralData);
        $end = pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        );

        return $localData.$centralData.$end;
    }

    /** @param array<int, array<string, mixed>> $register */
    public function registerCsv(array $register): string
    {
        $rows = [[
            'Sheet',
            'Drawing Number',
            'Title',
            'Revision',
        ]];

        foreach ($register as $row) {
            $rows[] = [
                (string) ($row['sheet'] ?? ''),
                (string) ($row['drawing_number'] ?? ''),
                (string) ($row['title'] ?? ''),
                (string) ($row['revision'] ?? ''),
            ];
        }

        return collect($rows)
            ->map(fn (array $row): string => collect($row)->map(fn (string $value): string => '"'.str_replace('"', '""', $value).'"')->implode(','))
            ->implode("\r\n")."\r\n";
    }

    /** @param array<string, string> $files */
    public function checksums(array $files): string
    {
        return collect($files)
            ->map(fn (string $contents, string $name): string => hash('sha256', $contents).'  '.$this->safeName($name))
            ->implode("\n")."\n";
    }

    /** @param array<string, mixed> $index */
    public function readme(array $index): string
    {
        return implode("\r\n", [
            'NEXORIN CONTROLLED SLD EXPORT PACKAGE',
            '=====================================',
            '',
            'Package ID: '.(string) ($index['package_id'] ?? 'TBC'),
            'Revision: '.(string) ($index['revision'] ?? 'TBC'),
            'Issue: '.(string) ($index['issue_code'] ?? 'DRAFT').' - '.(string) ($index['issue_label'] ?? 'Draft'),
            'Disposition: '.(string) ($index['disposition'] ?? 'NOT FOR CONSTRUCTION'),
            'Generated: '.(string) ($index['generated_at'] ?? ''),
            '',
            'Contents:',
            '- Controlled multi-sheet PDF',
            '- Combined editable SVG',
            '- Export and release manifest JSON',
            '- Six-sheet drawing register CSV',
            '- SHA-256 checksum list',
            '',
            'This package does not constitute an IEC certification or construction release unless the manifest disposition explicitly states otherwise.',
            '',
        ]);
    }

    private function safeName(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        $name = preg_replace('~/+~', '/', $name) ?: '';
        $segments = collect(explode('/', $name))
            ->filter(fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..')
            ->map(fn (string $segment): string => Str::of($segment)->replaceMatches('/[\x00-\x1F\x7F]/u', '')->toString())
            ->filter();

        return $segments->implode('/');
    }

    /** @return array{0:int,1:int} */
    private function dosDateTime(): array
    {
        $now = now();
        $year = max(1980, min(2107, $now->year));
        $dosTime = ($now->hour << 11) | ($now->minute << 5) | intdiv($now->second, 2);
        $dosDate = (($year - 1980) << 9) | ($now->month << 5) | $now->day;

        return [$dosTime, $dosDate];
    }
}
