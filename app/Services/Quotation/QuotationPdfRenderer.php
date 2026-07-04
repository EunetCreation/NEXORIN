<?php

namespace App\Services\Quotation;

use App\Models\Quotation;
use App\Support\PhoneFormatter;
use DateTimeInterface;

class QuotationPdfRenderer
{
    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN = 42.0;
    private const BOTTOM_MARGIN = 56.0;

    /** @var array<int, string> */
    private array $pages = [];

    private string $content = '';

    private float $y = 0.0;

    public function render(Quotation $quotation): string
    {
        if ($quotation->exists) {
            $quotation->loadMissing(['items', 'project.customer', 'preparer', 'issuer']);
        }

        $this->pages = [];
        $this->content = '';
        $this->newPage();

        $this->drawHeader($quotation);
        $this->sectionTitle('Customer and Project');
        $this->twoColumnRows([
            ['Customer', $this->value($quotation->customer_snapshot['name'] ?? $quotation->project?->customer?->name)],
            ['Email', $this->value($quotation->customer_snapshot['email'] ?? $quotation->project?->customer?->email)],
            ['Phone', $this->formatPhone($quotation->customer_snapshot['phone'] ?? $quotation->project?->customer?->phone)],
            ['Project', $this->value($quotation->project_snapshot['name'] ?? $quotation->project?->name)],
            ['Project No.', $this->value($quotation->project_snapshot['project_number'] ?? $quotation->project?->project_number)],
            ['Site', $this->value($quotation->project_snapshot['site_address'] ?? $quotation->project?->site_address)],
        ]);

        $this->sectionTitle('Technical Summary');
        $technical = $quotation->technical_summary ?? [];
        $this->twoColumnRows([
            ['System type', $this->label($technical['system_type'] ?? null)],
            ['Installed PV', $this->number($technical['installed_pv_kw'] ?? null, 2).' kWp'],
            ['Annual energy', $this->number($technical['annual_energy_kwh'] ?? null, 0).' kWh/year'],
            ['PV modules', $this->number($technical['module_count'] ?? null, 0).' pcs'],
            ['Inverters', $this->number($technical['inverter_count'] ?? null, 0).' unit'],
            ['Battery', $this->number($technical['battery_nominal_kwh'] ?? null, 2).' kWh'],
        ]);

        $this->sectionTitle('Financial Highlights');
        $financial = $quotation->financial_summary ?? [];
        $this->twoColumnRows([
            ['CAPEX', $this->money($financial['project_capex_idr'] ?? 0)],
            ['First year saving', $this->money($financial['first_year_saving_idr'] ?? 0)],
            ['Simple payback', $this->nullableNumber($financial['simple_payback_years'] ?? null, 2, ' years')],
            ['ROI', $this->nullableNumber($financial['lifetime_roi_percent'] ?? null, 2, '%')],
            ['NPV', $this->money($financial['npv_idr'] ?? 0)],
            ['LCOE', $this->nullableNumber($financial['lcoe_idr_per_kwh'] ?? null, 2, ' IDR/kWh')],
        ]);

        $this->ensure(106);
        $this->sectionTitle('Commercial Offer');
        $this->drawItemsTable($quotation);

        $this->sectionTitle('Terms and Notes');
        $terms = $quotation->terms ?? [];
        $this->termBlock('Payment terms', $terms['payment_terms'] ?? null);
        $this->termBlock('Delivery terms', $terms['delivery_terms'] ?? null);
        $this->termBlock('Warranty terms', $terms['warranty_terms'] ?? null);
        $this->termBlock('Exclusions', $terms['exclusions'] ?? null);

        if (! empty($terms['quotation_note'])) {
            $this->termBlock('Note', $terms['quotation_note']);
        }

        if (! empty($quotation->warnings)) {
            $this->sectionTitle('Warnings');
            foreach ($quotation->warnings as $warning) {
                $this->bullet((string) $warning);
            }
        }

        $this->sectionTitle('Disclaimer');
        $this->paragraph('This quotation is generated from NEXORIN preliminary engineering data, BOQ pricing, and financial analysis. Final implementation remains subject to site survey, utility approval, structural verification, product availability, and authorized engineer review.');

        $this->finishPage();

        return $this->buildPdf();
    }

    public function filename(Quotation $quotation): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-_]+/', '-', (string) ($quotation->quote_number ?: 'quotation'));

        return trim($number, '-') . '.pdf';
    }

    private function drawHeader(Quotation $quotation): void
    {
        $commercial = $quotation->commercial_summary ?? [];
        $quoteNumber = (string) ($quotation->quote_number ?: 'Draft quotation');
        $status = strtoupper((string) ($quotation->status_label ?? $quotation->status ?? 'draft'));
        $validUntil = $quotation->valid_until instanceof DateTimeInterface ? $quotation->valid_until->format('d M Y') : $this->value($commercial['valid_until'] ?? null);
        $issuedAt = $quotation->issued_at instanceof DateTimeInterface ? $quotation->issued_at->format('d M Y H:i') : '-';

        $this->rect(0, self::PAGE_HEIGHT - 122, self::PAGE_WIDTH, 122, '0.02 0.15 0.22', true);
        $this->text(self::MARGIN, 792, 'NEXORIN', 22, 'F2', '1 1 1');
        $this->text(self::MARGIN, 774, 'Solar Engineering and Quotation Platform', 9, 'F1', '0.80 0.93 0.92');
        $this->text(390, 793, $quoteNumber, 14, 'F2', '1 1 1', 'right');
        $this->text(390, 776, 'Revision '.$quotation->revision_number.' | '.$status, 9, 'F1', '0.80 0.93 0.92', 'right');
        $this->text(390, 759, 'Valid until: '.$validUntil, 9, 'F1', '0.80 0.93 0.92', 'right');
        $this->text(390, 742, 'Issued: '.$issuedAt, 9, 'F1', '0.80 0.93 0.92', 'right');
        $this->y = 692;
    }

    /** @param array<int, array{0:string,1:string}> $rows */
    private function twoColumnRows(array $rows): void
    {
        $leftX = self::MARGIN;
        $rightX = 310;
        $rowWidth = 235;
        $i = 0;

        while ($i < count($rows)) {
            $leftHeight = $this->fieldHeight($rowWidth, $rows[$i][1]);
            $rightHeight = isset($rows[$i + 1]) ? $this->fieldHeight($rowWidth, $rows[$i + 1][1]) : 0;
            $height = max($leftHeight, $rightHeight, 28);

            $this->ensure($height + 6);
            $this->fieldRow($leftX, $this->y, $rowWidth, $height, $rows[$i][0], $rows[$i][1]);

            if (isset($rows[$i + 1])) {
                $this->fieldRow($rightX, $this->y, $rowWidth, $height, $rows[$i + 1][0], $rows[$i + 1][1]);
            }

            $this->y -= $height + 6;
            $i += 2;
        }

        $this->y -= 4;
    }

    private function fieldHeight(float $width, string $value): float
    {
        $lineCount = count($this->wrap($value, $this->charLimit($width - 16, 9.5)));

        return 20 + ($lineCount * 11);
    }

    private function fieldRow(float $x, float $y, float $width, float $height, string $label, string $value): void
    {
        $this->rect($x, $y - $height + 6, $width, $height, '0.96 0.98 0.98', true);
        $this->rect($x, $y - $height + 6, $width, $height, '0.86 0.90 0.91', false);
        $this->text($x + 8, $y - 2, $label, 7.5, 'F2', '0.33 0.40 0.44');

        $lineY = $y - 15;
        foreach ($this->wrap($value, $this->charLimit($width - 16, 9.5)) as $line) {
            $this->text($x + 8, $lineY, $line, 9.5, 'F1', '0.05 0.12 0.17');
            $lineY -= 11;
        }
    }

    private function drawItemsTable(Quotation $quotation): void
    {
        $this->ensure(70);
        $this->tableHeader();

        foreach ($quotation->items as $item) {
            $name = (string) $item->item_name;
            $description = (string) ($item->description ?: $item->item_name);
            $nameLines = $this->wrap($name, 44);
            $descriptionLines = $this->wrap($description, 58);
            $height = max(34, 18 + (count($nameLines) * 10) + (count($descriptionLines) * 9));

            if ($this->y - $height < self::BOTTOM_MARGIN) {
                $this->newPage();
                $this->sectionTitle('Commercial Offer - continued');
                $this->tableHeader();
            }

            $top = $this->y;
            $this->rect(self::MARGIN, $top - $height + 6, 511, $height, '0.90 0.93 0.94', false);

            $lineY = $top - 10;
            foreach ($nameLines as $line) {
                $this->text(50, $lineY, $line, 9, 'F2');
                $lineY -= 10;
            }

            $lineY -= 2;
            foreach ($descriptionLines as $line) {
                $this->text(50, $lineY, $line, 7.5, 'F1', '0.39 0.45 0.50');
                $lineY -= 9;
            }

            $this->text(290, $top - 12, $this->number($item->quantity, 3), 8.5, 'F1', '0.05 0.12 0.17', 'right');
            $this->text(335, $top - 12, (string) $item->unit, 8.5);
            $this->text(450, $top - 12, $this->money($item->unit_price_idr), 8.5, 'F1', '0.05 0.12 0.17', 'right');
            $this->text(553, $top - 12, $this->money($item->line_price_idr), 8.5, 'F2', '0.05 0.12 0.17', 'right');
            $this->y -= $height + 4;
        }

        $commercial = $quotation->commercial_summary ?? [];
        $grandTotal = (float) ($commercial['grand_total_idr'] ?? $quotation->items->sum('line_price_idr'));
        $this->ensure(42);
        $this->rect(330, $this->y - 28, 223, 34, '0.02 0.15 0.22', true);
        $this->text(345, $this->y - 9, 'Grand Total', 11, 'F2', '1 1 1');
        $this->text(540, $this->y - 9, $this->money($grandTotal), 11, 'F2', '1 1 1', 'right');
        $this->y -= 48;
    }

    private function tableHeader(): void
    {
        $this->rect(self::MARGIN, $this->y - 18, 511, 24, '0.88 0.94 0.94', true);
        $this->text(50, $this->y - 8, 'Item', 8, 'F2', '0.05 0.12 0.17');
        $this->text(290, $this->y - 8, 'Qty', 8, 'F2', '0.05 0.12 0.17', 'right');
        $this->text(335, $this->y - 8, 'Unit', 8, 'F2', '0.05 0.12 0.17');
        $this->text(450, $this->y - 8, 'Unit Price', 8, 'F2', '0.05 0.12 0.17', 'right');
        $this->text(553, $this->y - 8, 'Line Total', 8, 'F2', '0.05 0.12 0.17', 'right');
        $this->y -= 32;
    }

    private function termBlock(string $title, ?string $body): void
    {
        $body = $this->value($body);
        $lines = $this->wrap($body, 92);
        $height = 24 + (count($lines) * 10);
        $this->ensure($height + 10);
        $this->text(self::MARGIN, $this->y, $title, 10, 'F2');
        $this->y -= 14;

        foreach ($lines as $line) {
            $this->text(self::MARGIN, $this->y, $line, 8.5, 'F1', '0.28 0.34 0.38');
            $this->y -= 10;
        }

        $this->y -= 8;
    }

    private function sectionTitle(string $title): void
    {
        $this->ensure(36);
        $this->text(self::MARGIN, $this->y, $title, 13, 'F2', '0.02 0.15 0.22');
        $this->line(self::MARGIN, $this->y - 8, 553, $this->y - 8, '0.78 0.86 0.86');
        $this->y -= 24;
    }

    private function paragraph(string $text): void
    {
        foreach ($this->wrap($text, 96) as $line) {
            $this->ensure(14);
            $this->text(self::MARGIN, $this->y, $line, 8.5, 'F1', '0.28 0.34 0.38');
            $this->y -= 11;
        }
    }

    private function bullet(string $text): void
    {
        foreach ($this->wrap($text, 90) as $index => $line) {
            $this->ensure(14);
            $prefix = $index === 0 ? '- ' : '  ';
            $this->text(self::MARGIN, $this->y, $prefix.$line, 8.5, 'F1', '0.45 0.21 0.06');
            $this->y -= 11;
        }
    }

    private function ensure(float $height): void
    {
        if ($this->y - $height < self::BOTTOM_MARGIN) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        if ($this->content !== '') {
            $this->finishPage();
        }

        $this->content = '';
        $this->y = self::PAGE_HEIGHT - self::MARGIN;
    }

    private function finishPage(): void
    {
        if ($this->content === '') {
            return;
        }

        $pageNo = count($this->pages) + 1;
        $this->line(self::MARGIN, 40, 553, 40, '0.86 0.90 0.91');
        $this->text(self::MARGIN, 25, 'Generated by NEXORIN - preliminary quotation document', 7.5, 'F1', '0.45 0.51 0.55');
        $this->text(553, 25, 'Page '.$pageNo, 7.5, 'F1', '0.45 0.51 0.55', 'right');
        $this->pages[] = $this->content;
        $this->content = '';
    }

    private function text(float $x, float $y, string $text, float $size = 10, string $font = 'F1', string $color = '0.05 0.12 0.17', string $align = 'left'): void
    {
        $text = $this->pdfText($text);
        $width = strlen($text) * $size * 0.48;

        if ($align === 'right') {
            $x -= $width;
        }

        $this->content .= "q\n{$color} rg\nBT /{$font} {$size} Tf 1 0 0 1 ".$this->fmt($x).' '.$this->fmt($y)." Tm ({$this->escape($text)}) Tj ET\nQ\n";
    }

    private function rect(float $x, float $y, float $w, float $h, string $color, bool $fill): void
    {
        $operator = $fill ? 'f' : 'S';
        $colorOperator = $fill ? 'rg' : 'RG';
        $this->content .= "q\n{$color} {$colorOperator}\n".$this->fmt($x).' '.$this->fmt($y).' '.$this->fmt($w).' '.$this->fmt($h)." re {$operator}\nQ\n";
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color): void
    {
        $this->content .= "q\n{$color} RG\n0.8 w\n".$this->fmt($x1).' '.$this->fmt($y1).' m '.$this->fmt($x2).' '.$this->fmt($y2)." l S\nQ\n";
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $limit): array
    {
        $limit = max(1, $limit);
        $text = trim(preg_replace('/\s+/', ' ', $this->value($text)) ?: '');

        if ($text === '') {
            return ['-'];
        }

        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if (strlen($word) > $limit) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                foreach (str_split($word, $limit) as $chunk) {
                    $lines[] = $chunk;
                }

                continue;
            }

            $candidate = trim($current.' '.$word);

            if (strlen($candidate) > $limit && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function charLimit(float $width, float $fontSize): int
    {
        return max(8, (int) floor($width / ($fontSize * 0.48)));
    }

    private function money(mixed $value): string
    {
        return 'IDR '.number_format((float) $value, 0, '.', ',');
    }

    private function number(mixed $value, int $decimals): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals, '.', ',');
    }

    private function nullableNumber(mixed $value, int $decimals, string $suffix): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $this->number($value, $decimals).$suffix;
    }

    private function label(mixed $value): string
    {
        $value = $this->value($value);

        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private function formatPhone(mixed $value): string
    {
        return PhoneFormatter::indonesia($value === null ? null : (string) $value) ?? '-';
    }

    private function value(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '-' : $value;
    }

    private function truncate(string $text, int $length): string
    {
        $text = $this->value($text);

        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }

    private function pdfText(string $text): string
    {
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);
        $text = str_replace(['–', '—', '−'], '-', $text);
        $text = str_replace(['“', '”'], '"', $text);
        $text = str_replace(['‘', '’'], "'", $text);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function fmt(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function buildPdf(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $kids = [];
        $objectId = 5;

        foreach ($this->pages as $pageContent) {
            $pageId = $objectId++;
            $contentId = $objectId++;
            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($pageContent)." >>\nstream\n{$pageContent}\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 ".($maxObject + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size ".($maxObject + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
