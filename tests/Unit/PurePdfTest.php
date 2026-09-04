<?php
declare(strict_types=1);

namespace Tests\Unit;

use Oshim\Testing\TestCase;
use Oshim\Billing\Pdf\PdfDocument;
use Oshim\Billing\Pdf\PdfInvoiceBuilder;

final class PurePdfTest extends TestCase
{
    public function testPdfDocumentBinaryGeneration(): void
    {
        $doc = new PdfDocument(595, 842);
        $doc->addText('OSHIM Sovereign Framework PDF', 50, 750, 16, 'F2', [0.0, 0.5, 0.9]);
        $doc->addRect(50, 700, 495, 30, [0.95, 0.95, 0.98]);
        $doc->addLine(50, 690, 545, 690, [0.2, 0.2, 0.2], 1.5);

        $pdfBinary = $doc->render();

        $this->assertStringStartsWith('%PDF-1.4', $pdfBinary);
        $this->assertStringEndsWith("%%EOF\n", $pdfBinary);
        $this->assertStringContainsString('/Type /Catalog', $pdfBinary);
        $this->assertStringContainsString('/Type /Pages', $pdfBinary);
        $this->assertStringContainsString('xref', $pdfBinary);
    }

    public function testPdfInvoiceBuilder(): void
    {
        $builder = new PdfInvoiceBuilder();
        $pdf = $builder->build([
            'invoice_number' => 'INV-2026-9901',
            'date' => '2026-08-30',
            'status' => 'PAID',
            'client_name' => 'Tech Enterprise Ltd',
            'client_email' => 'tech@enterprise.com',
            'items' => [
                ['description' => 'Dedicated KVM Node (16 Cores, 64GB RAM)', 'qty' => 2, 'price' => 150.00],
                ['description' => 'Managed Sovereign DNS Zone', 'qty' => 1, 'price' => 10.00],
            ],
            'currency' => '$',
        ]);

        $rendered = $pdf->render();
        $this->assertStringStartsWith('%PDF-1.4', $rendered);
        $this->assertStringContainsString('INV-2026-9901', $rendered);
        $this->assertStringContainsString('PAID', $rendered);
    }
}
