<?php

namespace App\Services\Sld;

use App\Models\ProjectSldRevision;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SldPackageArchive
{
    public const DISK = 'local';

    private const ROOT = 'sld-packages/';

    /** @return array<string, mixed> */
    public function store(ProjectSldRevision $revision, string $eventId, string $filename, string $bytes): array
    {
        $safeEvent = Str::lower(Str::slug($eventId, '-')) ?: Str::lower((string) Str::uuid());
        $safeFilename = basename(str_replace('\\', '/', $filename));
        $path = sprintf(
            self::ROOT.'company-%d/project-%d/R%d/%s/%s',
            (int) $revision->company_id,
            (int) $revision->project_id,
            (int) $revision->revision,
            $safeEvent,
            $safeFilename
        );

        if (! Storage::disk(self::DISK)->put($path, $bytes)) {
            throw new \RuntimeException('The controlled package could not be written to private storage.');
        }

        return [
            'disk' => self::DISK,
            'path' => $path,
            'filename' => $safeFilename,
            'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'stored_at' => now()->toISOString(),
        ];
    }

    public function exists(array $archive): bool
    {
        try {
            [$disk, $path] = $this->validatedLocation($archive);
        } catch (\RuntimeException) {
            return false;
        }

        return Storage::disk($disk)->exists($path);
    }

    public function read(array $archive): string
    {
        [$disk, $path] = $this->validatedLocation($archive);
        if (! Storage::disk($disk)->exists($path)) {
            throw new \RuntimeException('The archived controlled package is missing from private storage.');
        }

        $bytes = Storage::disk($disk)->get($path);
        if (! is_string($bytes)) {
            throw new \RuntimeException('The archived controlled package could not be read.');
        }

        return $bytes;
    }

    /** @return array<string, mixed> */
    public function verify(array $archive, string $expectedSha256): array
    {
        if (! $this->exists($archive)) {
            return [
                'status' => 'missing',
                'expected_sha256' => $expectedSha256,
                'actual_sha256' => null,
                'verified_at' => now()->toISOString(),
            ];
        }

        $bytes = $this->read($archive);
        $actual = hash('sha256', $bytes);

        return [
            'status' => hash_equals(strtolower($expectedSha256), strtolower($actual)) ? 'verified' : 'mismatch',
            'expected_sha256' => $expectedSha256,
            'actual_sha256' => $actual,
            'byte_size' => strlen($bytes),
            'verified_at' => now()->toISOString(),
        ];
    }

    /** @return array{0:string,1:string} */
    private function validatedLocation(array $archive): array
    {
        $disk = (string) ($archive['disk'] ?? self::DISK);
        $path = ltrim(str_replace('\\', '/', (string) ($archive['path'] ?? '')), '/');

        if ($disk !== self::DISK) {
            throw new \RuntimeException('Controlled SLD packages may only be read from private local storage.');
        }
        if ($path === '' || ! str_starts_with($path, self::ROOT) || str_contains($path, '../')) {
            throw new \RuntimeException('The archived controlled package path is invalid.');
        }

        return [$disk, $path];
    }
}
