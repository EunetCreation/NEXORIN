<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class HtmlToPdfRenderer
{
    /**
     * Render a complete HTML document into a PDF binary using Playwright.
     *
     * This version intentionally avoids Symfony Process on Windows. Symfony's
     * Windows pipe implementation may try to create lock files in C:\Windows
     * when PHP does not have a writable TEMP/TMP directory. Raw proc_open with
     * explicit pipes is more predictable for local Windows development and
     * Windows server deployments.
     */
    public function render(string $html, array $options = []): string
    {
        $this->ensureScriptExists();

        $workDir = storage_path('app/pdf-renderer');
        $htmlDir = $workDir.'/html';
        $outputDir = $workDir.'/output';
        $failedDir = $workDir.'/failed';
        $processTempDir = $this->processTempDir($workDir);

        File::ensureDirectoryExists($htmlDir);
        File::ensureDirectoryExists($outputDir);
        File::ensureDirectoryExists($failedDir);
        File::ensureDirectoryExists($processTempDir);

        $token = now()->format('YmdHis').'-'.Str::uuid()->toString();
        $htmlPath = $htmlDir.'/'.$token.'.html';
        $pdfPath = $outputDir.'/'.$token.'.pdf';

        File::put($htmlPath, $html);

        $payload = array_merge([
            'navigationTimeout' => (int) config('nexorin_pdf.navigation_timeout_ms', 60000),
            'viewportWidth' => (int) config('nexorin_pdf.viewport_width', 1240),
            'viewportHeight' => (int) config('nexorin_pdf.viewport_height', 1754),
        ], $options);

        $nodeBinary = $this->resolveNodeBinary();
        $scriptPath = $this->resolveRenderScript();
        $timeout = (int) config('nexorin_pdf.timeout_seconds', 120);

        $command = [
            $nodeBinary,
            $scriptPath,
            $htmlPath,
            $pdfPath,
            json_encode($payload, JSON_THROW_ON_ERROR),
        ];

        try {
            $result = $this->runProcess(
                command: $command,
                cwd: base_path(),
                env: $this->processEnvironment($nodeBinary, $processTempDir),
                timeoutSeconds: $timeout
            );
        } catch (Throwable $exception) {
            $failedHtml = $this->saveFailedHtml($htmlPath, $failedDir, $token);
            $this->cleanup($htmlPath, $pdfPath);

            throw new PdfRenderingException(
                'Premium PDF rendering failed before Chromium completed: '.$exception->getMessage().
                ' | Failed HTML saved at: '.$failedHtml,
                0,
                $exception
            );
        }

        if ($result['exitCode'] !== 0) {
            $failedHtml = $this->saveFailedHtml($htmlPath, $failedDir, $token);
            $this->cleanup($htmlPath, $pdfPath);

            throw new PdfRenderingException(
                'Premium PDF rendering failed with exit code '.$result['exitCode'].': '.
                $this->formatProcessOutput($result['stdout'], $result['stderr']).
                ' | Failed HTML saved at: '.$failedHtml
            );
        }

        if (! File::exists($pdfPath) || File::size($pdfPath) < 1024) {
            $failedHtml = $this->saveFailedHtml($htmlPath, $failedDir, $token);
            $this->cleanup($htmlPath, $pdfPath);

            throw new PdfRenderingException(
                'Premium PDF rendering did not produce a valid PDF file. '.
                'Node stdout/stderr: '.$this->formatProcessOutput($result['stdout'], $result['stderr']).
                ' | Failed HTML saved at: '.$failedHtml
            );
        }

        $pdf = File::get($pdfPath);
        $this->cleanup($htmlPath, $pdfPath);

        return $pdf;
    }

    private function ensureScriptExists(): void
    {
        $script = $this->resolveRenderScript();

        if (! File::exists($script)) {
            throw new PdfRenderingException('Premium PDF render script is missing: '.$script);
        }
    }

    private function resolveRenderScript(): string
    {
        $configured = (string) config('nexorin_pdf.render_script', base_path('scripts/render-pdf.mjs'));

        return realpath($configured) ?: $configured;
    }

    /**
     * Resolve Node safely. This is important on Windows because php artisan serve
     * may not inherit the updated PATH after Node was reinstalled.
     */
    private function resolveNodeBinary(): string
    {
        $configured = trim((string) config('nexorin_pdf.node_binary', 'node'));

        if ($configured !== '' && $configured !== 'node') {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = array_filter([
                getenv('ProgramFiles') ? getenv('ProgramFiles').'\\nodejs\\node.exe' : null,
                getenv('ProgramFiles(x86)') ? getenv('ProgramFiles(x86)').'\\nodejs\\node.exe' : null,
                getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA').'\\Programs\\nodejs\\node.exe' : null,
            ]);

            foreach ($candidates as $candidate) {
                if (File::exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return $configured ?: 'node';
    }

    private function processTempDir(string $workDir): string
    {
        $configured = trim((string) config('nexorin_pdf.temp_dir', ''));

        return $configured !== '' ? $configured : $workDir.'/process-temp';
    }

    /**
     * @return array<string, string>
     */
    private function processEnvironment(string $nodeBinary, string $processTempDir): array
    {
        $env = getenv();

        if (! is_array($env)) {
            $env = [];
        }

        $nodeDir = dirname($nodeBinary);
        $currentPath = $env['PATH'] ?? $env['Path'] ?? getenv('PATH') ?: getenv('Path') ?: '';

        if ($nodeDir && $nodeDir !== '.' && ! str_contains(strtolower((string) $currentPath), strtolower($nodeDir))) {
            $currentPath = $nodeDir.PATH_SEPARATOR.$currentPath;
        }

        $env['PATH'] = (string) $currentPath;
        $env['Path'] = (string) $currentPath;

        // Prevent Symfony/Node/Chromium tooling from falling back to C:\Windows.
        $env['TEMP'] = $processTempDir;
        $env['TMP'] = $processTempDir;
        $env['TMPDIR'] = $processTempDir;

        $chromiumPath = trim((string) config('nexorin_pdf.chromium_path', ''));

        if ($chromiumPath !== '') {
            if (! File::exists($chromiumPath)) {
                throw new PdfRenderingException('Configured Chromium executable does not exist: '.$chromiumPath);
            }

            $env['NEXORIN_CHROMIUM_PATH'] = $chromiumPath;
        }

        putenv('TEMP='.$processTempDir);
        putenv('TMP='.$processTempDir);
        putenv('TMPDIR='.$processTempDir);
        $_ENV['TEMP'] = $processTempDir;
        $_ENV['TMP'] = $processTempDir;
        $_ENV['TMPDIR'] = $processTempDir;
        $_SERVER['TEMP'] = $processTempDir;
        $_SERVER['TMP'] = $processTempDir;
        $_SERVER['TMPDIR'] = $processTempDir;

        return $env;
    }

    /**
     * Run the Node/Playwright process without Symfony Process.
     *
     * @param array<int, string> $command
     * @param array<string, string> $env
     * @return array{exitCode:int, stdout:string, stderr:string, timedOut:bool}
     */
    private function runProcess(array $command, string $cwd, array $env, int $timeoutSeconds): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $options = PHP_OS_FAMILY === 'Windows'
            ? ['bypass_shell' => true]
            : [];

        $process = @proc_open($command, $descriptorSpec, $pipes, $cwd, $env, $options);

        if (! is_resource($process)) {
            throw new PdfRenderingException('Unable to start Node process. Command: '.$this->commandForDisplay($command));
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $exitCodeFromStatus = null;
        $startedAt = microtime(true);

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            $status = proc_get_status($process);
            $running = (bool) ($status['running'] ?? false);

            if (! $running) {
                $statusExitCode = $status['exitcode'] ?? null;
                $exitCodeFromStatus = is_int($statusExitCode) && $statusExitCode !== -1 ? $statusExitCode : null;
                break;
            }

            if ((microtime(true) - $startedAt) > $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process);
                usleep(500000);

                $status = proc_get_status($process);
                if ((bool) ($status['running'] ?? false)) {
                    proc_terminate($process, 9);
                }

                break;
            }

            usleep(100000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $closeCode = proc_close($process);
        $exitCode = $exitCodeFromStatus ?? ($timedOut ? 124 : $closeCode);

        if ($timedOut) {
            $stderr = trim($stderr."\nProcess timed out after {$timeoutSeconds} seconds.");
        }

        return [
            'exitCode' => (int) $exitCode,
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'timedOut' => $timedOut,
        ];
    }

    private function saveFailedHtml(string $htmlPath, string $failedDir, string $token): string
    {
        $failedHtml = $failedDir.'/'.$token.'.html';

        if (File::exists($htmlPath)) {
            File::copy($htmlPath, $failedHtml);
        }

        return $failedHtml;
    }

    private function formatProcessOutput(string $stdout, string $stderr): string
    {
        $parts = [];

        if (trim($stderr) !== '') {
            $parts[] = 'stderr: '.trim($stderr);
        }

        if (trim($stdout) !== '') {
            $parts[] = 'stdout: '.trim($stdout);
        }

        return $parts !== [] ? implode(' | ', $parts) : 'No output from Node process.';
    }

    /**
     * @param array<int, string> $command
     */
    private function commandForDisplay(array $command): string
    {
        return implode(' ', array_map(static function (string $part): string {
            return str_contains($part, ' ') ? '"'.$part.'"' : $part;
        }, $command));
    }

    private function cleanup(string $htmlPath, string $pdfPath): void
    {
        if (! (bool) config('nexorin_pdf.keep_temp_html', false)) {
            File::delete($htmlPath);
        }

        File::delete($pdfPath);
    }
}
