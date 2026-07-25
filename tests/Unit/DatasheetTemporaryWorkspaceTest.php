<?php

namespace Tests\Unit;

use App\Services\Catalog\DatasheetTemporaryWorkspace;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class DatasheetTemporaryWorkspaceTest extends TestCase
{
    private string $workspaceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspaceDirectory = storage_path('framework/testing/datasheet-workspace-'.bin2hex(random_bytes(6)));
        config()->set('nexorin_catalog.datasheet_parser.temporary_directory', $this->workspaceDirectory);
    }

    protected function tearDown(): void
    {
        if (is_file($this->workspaceDirectory)) {
            @unlink($this->workspaceDirectory);
        } else {
            File::deleteDirectory($this->workspaceDirectory);
        }

        parent::tearDown();
    }

    public function test_it_creates_a_protected_workspace_without_using_the_php_system_temp_directory(): void
    {
        $workspace = app(DatasheetTemporaryWorkspace::class);

        $path = $workspace->createPath();

        $this->assertDirectoryExists($this->workspaceDirectory);
        $this->assertFileExists($path);
        $this->assertSame(
            realpath($this->workspaceDirectory),
            realpath(dirname($path))
        );

        $workspace->remove($path);
        $this->assertFileDoesNotExist($path);
    }

    public function test_it_creates_unique_atomic_workspace_files(): void
    {
        $workspace = app(DatasheetTemporaryWorkspace::class);

        $first = $workspace->createPath();
        $second = $workspace->createPath();

        $this->assertNotSame($first, $second);
        $this->assertFileExists($first);
        $this->assertFileExists($second);

        $workspace->remove($first);
        $workspace->remove($second);
    }

    public function test_it_rejects_a_workspace_path_that_is_an_existing_file(): void
    {
        File::ensureDirectoryExists(dirname($this->workspaceDirectory));
        file_put_contents($this->workspaceDirectory, 'not-a-directory');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a directory');

        app(DatasheetTemporaryWorkspace::class)->createPath();
    }
}
