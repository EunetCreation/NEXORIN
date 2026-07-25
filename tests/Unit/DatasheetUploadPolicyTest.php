<?php

namespace Tests\Unit;

use App\Services\Catalog\DatasheetUploadPolicy;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatasheetUploadPolicyTest extends TestCase
{
    public function test_effective_limit_uses_the_smallest_application_and_php_runtime_limit(): void
    {
        $policy = new DatasheetUploadPolicy(
            applicationMaxKb: 10240,
            runtimeIni: [
                'file_uploads' => '1',
                'upload_max_filesize' => '1M',
                'post_max_size' => '8M',
            ],
            temporaryDirectory: sys_get_temp_dir(),
            temporaryDirectoryWritable: true,
        );

        $summary = $policy->summary();

        $this->assertSame(1024, $summary['effective_limit_kb']);
        $this->assertSame('1 MB', $summary['effective_limit_label']);
        $this->assertTrue($summary['runtime_cap_active']);
        $this->assertNotEmpty($summary['warnings']);
    }

    public function test_php_upload_error_is_translated_into_an_actionable_message(): void
    {
        $policy = new DatasheetUploadPolicy(
            applicationMaxKb: 10240,
            runtimeIni: [
                'file_uploads' => '1',
                'upload_max_filesize' => '1M',
                'post_max_size' => '8M',
            ],
            temporaryDirectory: sys_get_temp_dir(),
            temporaryDirectoryWritable: true,
        );
        $file = new UploadedFile('', 'vendor.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $message = $policy->uploadErrorMessage($file);

        $this->assertStringContainsString('upload_max_filesize (1 MB)', $message);
        $this->assertStringContainsString('did not reach the parser', $message);
    }

    #[DataProvider('iniSizeProvider')]
    public function test_ini_size_values_are_normalized_to_kilobytes(string $value, int $expected): void
    {
        $policy = new DatasheetUploadPolicy();

        $this->assertSame($expected, $policy->iniSizeToKb($value));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function iniSizeProvider(): array
    {
        return [
            'kilobytes' => ['512K', 512],
            'megabytes' => ['2M', 2048],
            'gigabytes' => ['1G', 1048576],
            'bytes' => ['1048576', 1024],
        ];
    }
}
