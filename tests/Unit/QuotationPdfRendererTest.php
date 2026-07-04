<?php

namespace Tests\Unit;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Quotation\QuotationPdfRenderer;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class QuotationPdfRendererTest extends TestCase
{
    public function test_it_renders_a_valid_customer_quotation_pdf(): void
    {
        $quotation = new Quotation([
            'quote_number' => 'QTN-DEMO-001',
            'revision_number' => 0,
            'status' => 'draft',
            'currency' => 'IDR',
            'title' => 'Solar PV System Proposal',
            'valid_until' => now()->addDays(14),
            'customer_snapshot' => [
                'name' => 'NEXORIN Customer',
                'email' => 'customer@nexorin.local',
                'phone' => '+62 0812-3456-7890',
            ],
            'project_snapshot' => [
                'project_number' => 'NXR-PRJ-001',
                'name' => 'Residential PV Demo with a Very Long Project Name for PDF Wrapping Validation',
                'site_address' => 'Jl. Solar No. 1, Pekanbaru, Kawasan Atap Timur yang Memiliki Nama Lokasi Sangat Panjang',
            ],
            'technical_summary' => [
                'system_type' => 'on_grid',
                'installed_pv_kw' => 6.6,
                'annual_energy_kwh' => 9500,
                'module_count' => 12,
                'inverter_count' => 1,
                'battery_nominal_kwh' => 0,
            ],
            'financial_summary' => [
                'project_capex_idr' => 60000000,
                'first_year_saving_idr' => 12000000,
                'simple_payback_years' => 5,
                'lifetime_roi_percent' => 240,
                'npv_idr' => 85000000,
                'lcoe_idr_per_kwh' => 650,
            ],
            'commercial_summary' => [
                'grand_total_idr' => 60000000,
                'valid_until' => now()->addDays(14)->toDateString(),
            ],
            'terms' => [
                'payment_terms' => '50% down payment and 50% before commissioning.',
                'delivery_terms' => 'Installation after site readiness confirmation.',
                'warranty_terms' => 'Manufacturer warranty applies.',
                'exclusions' => 'Utility approval is excluded.',
                'quotation_note' => 'Prepared for preliminary approval.',
            ],
            'warnings' => [],
        ]);

        $quotation->setRelation('items', new Collection([
            new QuotationItem([
                'category' => 'pv_module',
                'item_code' => 'PV-MODULE',
                'item_name' => 'PV Module 550 Wp with extended product naming for layout validation',
                'description' => 'Monocrystalline PV module with long description that must wrap cleanly across multiple lines without being cut from the quotation table.',
                'quantity' => 12,
                'unit' => 'pcs',
                'unit_price_idr' => 1900000,
                'line_price_idr' => 22800000,
            ]),
            new QuotationItem([
                'category' => 'inverter',
                'item_code' => 'INV-GRID',
                'item_name' => 'Grid inverter',
                'description' => '5 kW inverter',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price_idr' => 20700000,
                'line_price_idr' => 20700000,
            ]),
        ]));

        $renderer = new QuotationPdfRenderer();
        $pdf = $renderer->render($quotation);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('NEXORIN', $pdf);
        $this->assertStringContainsString('QTN-DEMO-001', $pdf);
        $this->assertStringContainsString('Residential PV Demo', $pdf);
        $this->assertStringContainsString('+62 812-3456-7890', $pdf);
        $this->assertStringContainsString('layout validation', $pdf);
        $this->assertStringNotContainsString('...', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertSame('QTN-DEMO-001.pdf', $renderer->filename($quotation));
    }
}
