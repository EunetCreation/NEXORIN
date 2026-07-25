<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Material;
use App\Models\MaterialCertificate;
use App\Models\MaterialDatasheet;
use App\Models\MaterialDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MaterialEvidenceController extends Controller
{
    public function datasheet(Company $company, string $material, MaterialDatasheet $datasheet): RedirectResponse|StreamedResponse
    {
        $material = $this->findVisibleMaterial($company, $material);
        abort_unless((int) $datasheet->material_id === (int) $material->getKey(), 404);

        return $this->sourceResponse(
            $datasheet->file_path,
            $datasheet->external_url,
            $datasheet->original_filename ?: $datasheet->title
        );
    }

    public function certificate(Company $company, string $material, MaterialCertificate $certificate): RedirectResponse|StreamedResponse
    {
        $material = $this->findVisibleMaterial($company, $material);
        abort_unless((int) $certificate->material_id === (int) $material->getKey(), 404);

        return $this->sourceResponse(
            $certificate->file_path,
            $certificate->external_url,
            $certificate->title
        );
    }

    public function document(Company $company, string $material, MaterialDocument $document): RedirectResponse|StreamedResponse
    {
        $material = $this->findVisibleMaterial($company, $material);
        abort_unless((int) $document->material_id === (int) $material->getKey(), 404);

        return $this->sourceResponse(
            $document->file_path,
            $document->external_url,
            $document->original_filename ?: $document->title
        );
    }

    private function sourceResponse(?string $filePath, ?string $externalUrl, string $downloadName): RedirectResponse|StreamedResponse
    {
        if (filled($filePath) && Storage::exists($filePath)) {
            return Storage::download($filePath, $this->safeDownloadName($downloadName, $filePath));
        }

        if (filled($externalUrl)) {
            return redirect()->away($externalUrl);
        }

        abort(404, 'The registered evidence source is unavailable.');
    }

    private function safeDownloadName(string $name, ?string $filePath): string
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '-', $name), " .-");
        $extension = pathinfo((string) $filePath, PATHINFO_EXTENSION);

        if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$extension;
        }

        return $name !== '' ? $name : 'material-evidence'.($extension !== '' ? '.'.$extension : '');
    }

    private function findVisibleMaterial(Company $company, string $materialId): Material
    {
        return Material::query()
            ->visibleForCompany($company)
            ->whereKey($materialId)
            ->firstOrFail();
    }
}
