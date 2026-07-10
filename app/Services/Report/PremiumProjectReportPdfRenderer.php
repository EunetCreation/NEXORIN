<?php

namespace App\Services\Report;

use App\Models\Project;
use App\Services\Pdf\HtmlToPdfRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class PremiumProjectReportPdfRenderer
{
    public function __construct(
        private readonly ProjectReportDataBuilder $dataBuilder,
        private readonly HtmlToPdfRenderer $pdfRenderer,
    ) {}

    public function render(Project $project, string $reportMode = 'internal'): string
    {
        return $this->pdfRenderer->render($this->html($project, 'file', $reportMode));
    }

    public function html(Project $project, string $assetMode = 'web', string $reportMode = 'internal'): string
    {
        $normalizedReportMode = $this->normalizeReportMode($reportMode);
        $view = $normalizedReportMode === 'client'
            ? 'pdf.premium.client-proposal'
            : 'pdf.premium.project-report';

        return View::make($view, [
            'data' => $this->dataBuilder->build($project, $normalizedReportMode),
            'assets' => $this->assets($assetMode),
            'assetMode' => $assetMode,
            'reportMode' => $normalizedReportMode,
            'inlineCss' => $assetMode === 'file' ? $this->inlineCss() : null,
        ])->render();
    }

    public function filename(Project $project, string $reportMode = 'internal'): string
    {
        $number = $project->project_number ?: 'project-'.$project->getKey();
        $safe = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $number) ?: 'project-report';
        $suffix = $this->normalizeReportMode($reportMode) === 'client'
            ? 'client-proposal'
            : 'premium-engineering-report';

        return strtoupper(trim($safe, '-')).'-'.$suffix.'.pdf';
    }

    private function normalizeReportMode(string $reportMode): string
    {
        $mode = strtolower(str_replace(['-', ' '], '_', $reportMode));

        return in_array($mode, ['client', 'client_proposal', 'proposal'], true) ? 'client' : 'internal';
    }

    /**
     * @return array<string, string>
     */
    private function assets(string $mode): array
    {
        return [
            'css' => $this->assetUri('css/nexorin-premium-pdf.css', $mode),
            'logo' => $this->assetUri('images/nexorin-logo.png', $mode),
            'hero' => $this->assetUri('images/nexorin-hero.png', $mode),
        ];
    }

    private function inlineCss(): string
    {
        $path = public_path('css/nexorin-premium-pdf.css');

        return File::exists($path) ? File::get($path) : '';
    }

    private function assetUri(string $path, string $mode): string
    {
        if ($mode === 'web') {
            return asset($path);
        }

        return $this->fileUri(public_path($path));
    }

    /**
     * Build a browser-safe local file URI. The first patch encoded the Windows
     * drive colon, which may break Chromium resource loading.
     */
    private function fileUri(string $path): string
    {
        $absolute = realpath($path) ?: $path;
        $normalized = str_replace('\\', '/', $absolute);
        $parts = array_map('rawurlencode', explode('/', $normalized));
        $encoded = implode('/', $parts);
        $encoded = preg_replace('/^([A-Za-z])%3A/', '$1:', $encoded);

        if (str_starts_with($encoded, '/')) {
            return 'file://'.$encoded;
        }

        return 'file:///'.$encoded;
    }
}
