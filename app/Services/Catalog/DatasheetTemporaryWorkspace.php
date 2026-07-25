<?php

namespace App\Services\Catalog;

use RuntimeException;
use Throwable;

final class DatasheetTemporaryWorkspace
{
    private const MAX_CREATE_ATTEMPTS = 8;

    public function createPath(): string
    {
        $directory = $this->directory();
        $this->ensureDirectory($directory);

        for ($attempt = 0; $attempt < self::MAX_CREATE_ATTEMPTS; $attempt++) {
            try {
                $filename = 'nexorin-datasheet-'.bin2hex(random_bytes(16)).'.tmp';
            } catch (Throwable $exception) {
                throw new RuntimeException('A secure parser workspace filename could not be generated.', 0, $exception);
            }

            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            $handle = @fopen($path, 'x+b');

            if ($handle === false) {
                continue;
            }

            fclose($handle);
            @chmod($path, 0600);

            return $path;
        }

        throw new RuntimeException('A temporary datasheet parser file could not be created in the configured workspace.');
    }

    public function remove(?string $path): void
    {
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return;
        }

        @unlink($path);
    }

    public function directory(): string
    {
        $configured = trim((string) config('nexorin_catalog.datasheet_parser.temporary_directory', ''));

        return $configured !== ''
            ? rtrim($configured, "\\/")
            : storage_path('framework/nexorin-datasheet-parser');
    }

    private function ensureDirectory(string $directory): void
    {
        if ($directory === '') {
            throw new RuntimeException('The datasheet parser workspace path is empty.');
        }

        if (file_exists($directory) && ! is_dir($directory)) {
            throw new RuntimeException('The configured datasheet parser workspace is not a directory.');
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The datasheet parser workspace directory could not be created.');
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('The datasheet parser workspace directory is not writable.');
        }
    }
}
