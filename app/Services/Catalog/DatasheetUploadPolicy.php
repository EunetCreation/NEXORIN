<?php

namespace App\Services\Catalog;

use Illuminate\Http\UploadedFile;

final class DatasheetUploadPolicy
{
    private const POST_OVERHEAD_KB = 128;

    /**
     * @param  array<string, string|int|bool|null>|null  $runtimeIni
     */
    public function __construct(
        private readonly ?int $applicationMaxKb = null,
        private readonly ?array $runtimeIni = null,
        private readonly ?string $temporaryDirectory = null,
        private readonly ?bool $temporaryDirectoryWritable = null,
    ) {
    }

    /**
     * @return array{
     *     application_limit_kb:int,
     *     upload_limit_kb:?int,
     *     post_limit_kb:?int,
     *     effective_limit_kb:int,
     *     effective_limit_bytes:int,
     *     application_limit_label:string,
     *     upload_limit_label:string,
     *     post_limit_label:string,
     *     effective_limit_label:string,
     *     runtime_cap_active:bool,
     *     file_uploads_enabled:bool,
     *     temporary_directory:string,
     *     temporary_directory_writable:bool,
     *     warnings:array<int, string>
     * }
     */
    public function summary(): array
    {
        $applicationLimitKb = max(1, $this->applicationMaxKb ?? (int) config('nexorin_catalog.datasheet_parser.max_upload_kb', 10240));
        $runtime = $this->runtimeSettings();
        $uploadLimitKb = $this->iniSizeToKb($runtime['upload_max_filesize'] ?? null);
        $postLimitKb = $this->iniSizeToKb($runtime['post_max_size'] ?? null);
        $postPayloadLimitKb = $postLimitKb === null
            ? null
            : max(1, $postLimitKb - min(self::POST_OVERHEAD_KB, max(1, (int) floor($postLimitKb * 0.05))));

        $limits = array_values(array_filter(
            [$applicationLimitKb, $uploadLimitKb, $postPayloadLimitKb],
            static fn (?int $value): bool => $value !== null && $value > 0
        ));
        $effectiveLimitKb = $limits === [] ? $applicationLimitKb : min($limits);
        $fileUploadsEnabled = filter_var($runtime['file_uploads'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        $temporaryDirectory = $this->temporaryDirectory ?? sys_get_temp_dir();
        $temporaryDirectoryWritable = $this->temporaryDirectoryWritable
            ?? ($temporaryDirectory !== '' && is_dir($temporaryDirectory) && is_writable($temporaryDirectory));

        $warnings = [];
        if (! $fileUploadsEnabled) {
            $warnings[] = 'PHP file uploads are disabled for the active web runtime.';
        }
        if ($uploadLimitKb !== null && $uploadLimitKb < $applicationLimitKb) {
            $warnings[] = sprintf(
                'PHP upload_max_filesize limits files to %s, below the NEXORIN application setting of %s.',
                $this->formatKb($uploadLimitKb),
                $this->formatKb($applicationLimitKb),
            );
        }
        if ($postPayloadLimitKb !== null && $postPayloadLimitKb < min($applicationLimitKb, $uploadLimitKb ?? PHP_INT_MAX)) {
            $warnings[] = sprintf(
                'PHP post_max_size leaves approximately %s for the multipart upload payload.',
                $this->formatKb($postPayloadLimitKb),
            );
        }
        if (! $temporaryDirectoryWritable) {
            $warnings[] = 'The PHP temporary upload directory is unavailable or not writable.';
        }

        return [
            'application_limit_kb' => $applicationLimitKb,
            'upload_limit_kb' => $uploadLimitKb,
            'post_limit_kb' => $postLimitKb,
            'effective_limit_kb' => $effectiveLimitKb,
            'effective_limit_bytes' => $effectiveLimitKb * 1024,
            'application_limit_label' => $this->formatKb($applicationLimitKb),
            'upload_limit_label' => $uploadLimitKb === null ? 'Unlimited / not reported' : $this->formatKb($uploadLimitKb),
            'post_limit_label' => $postLimitKb === null ? 'Unlimited / not reported' : $this->formatKb($postLimitKb),
            'effective_limit_label' => $this->formatKb($effectiveLimitKb),
            'runtime_cap_active' => $effectiveLimitKb < $applicationLimitKb,
            'file_uploads_enabled' => $fileUploadsEnabled,
            'temporary_directory' => $temporaryDirectory,
            'temporary_directory_writable' => $temporaryDirectoryWritable,
            'warnings' => $warnings,
        ];
    }

    public function effectiveLimitKb(): int
    {
        return $this->summary()['effective_limit_kb'];
    }

    public function uploadErrorMessage(UploadedFile $file): string
    {
        $summary = $this->summary();

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE => sprintf(
                'The web server rejected the datasheet because it exceeds PHP upload_max_filesize (%s). NEXORIN did not reach the parser. Increase upload_max_filesize and post_max_size in the php.ini used by the web server, restart it, then upload again.',
                $summary['upload_limit_label'],
            ),
            UPLOAD_ERR_FORM_SIZE => 'The datasheet exceeds the upload limit declared by the browser form. Reload the material page and try again with a smaller file.',
            UPLOAD_ERR_PARTIAL => 'The datasheet upload was interrupted and only part of the file reached the server. Check the connection and upload the file again.',
            UPLOAD_ERR_NO_FILE => 'No datasheet file reached the server. Select the file again before submitting.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP cannot receive the datasheet because its temporary upload directory is missing. Configure upload_tmp_dir or restore the system temporary directory, then restart the web server.',
            UPLOAD_ERR_CANT_WRITE => 'PHP received the datasheet but could not write it to the temporary upload directory. Check free disk space and write permission for the PHP temporary directory.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the datasheet upload before NEXORIN could process it. Review the active PHP extensions and server log.',
            default => sprintf(
                'The web server rejected the datasheet before NEXORIN could parse it (PHP upload error %d). Check PHP upload limits, temporary-directory access, free disk space, and the web-server log.',
                $file->getError(),
            ),
        };
    }

    public function fileUploadsDisabledMessage(): string
    {
        return 'PHP file uploads are disabled for the active web runtime. Enable file_uploads in the php.ini used by the web server, restart it, then upload the datasheet again.';
    }

    public function iniSizeToKb(string|int|bool|null $value): ?int
    {
        if ($value === null || $value === false) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([kmgt]?)b?$/i', $normalized, $matches)) {
            return null;
        }

        $number = (float) $matches[1];
        if ($number <= 0) {
            return null;
        }

        $multiplier = match (strtolower($matches[2] ?? '')) {
            't' => 1024 * 1024 * 1024,
            'g' => 1024 * 1024,
            'm' => 1024,
            'k' => 1,
            default => 1 / 1024,
        };

        return max(1, (int) floor($number * $multiplier));
    }

    private function formatKb(int $kilobytes): string
    {
        if ($kilobytes >= 1024) {
            $megabytes = $kilobytes / 1024;
            $precision = $megabytes >= 10 ? 0 : 2;
            $formatted = number_format($megabytes, $precision, '.', '');
            if ($precision > 0) {
                $formatted = rtrim(rtrim($formatted, '0'), '.');
            }

            return $formatted.' MB';
        }

        return number_format($kilobytes).' KB';
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    private function runtimeSettings(): array
    {
        return $this->runtimeIni ?? [
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];
    }
}
