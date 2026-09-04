<?php
declare(strict_types=1);

namespace Oshim\Billing\Pdf;

use Oshim\Billing\Invoice;

/**
 * Branded Vector PDF Invoice Builder.
 */
class PdfInvoiceBuilder
{
    private PdfDocument $pdf;

    public function __construct()
    {
        $this->pdf = new PdfDocument(595, 842); // A4
    }

    public function build(array $invoiceData): PdfDocument
    {
        $pdf = $this->pdf;
        $width = $pdf->getPageWidth();
        $height = $pdf->getPageHeight();

        // 1. Top Header Banner
        $pdf->addRect(0, $height - 80, $width, 80, [0.03, 0.05, 0.12]);
        $pdf->addText('OSHIM CLOUD SOVEREIGN PLATFORM', 40, $height - 45, 18, 'F2', [0.0, 0.95, 1.0]);
        $pdf->addText('INVOICE / RECEIPT', 440, $height - 45, 14, 'F2', [1.0, 1.0, 1.0]);

        // 2. Invoice Meta Info
        $invoiceNumber = $invoiceData['invoice_number'] ?? 'INV-' . date('Ymd') . '-001';
        $issueDate = $invoiceData['date'] ?? date('Y-m-d');
        $status = strtoupper($invoiceData['status'] ?? 'PAID');

        $pdf->addText("Invoice #: {$invoiceNumber}", 40, $height - 110, 11, 'F2', [0.1, 0.1, 0.2]);
        $pdf->addText("Issue Date: {$issueDate}", 40, $height - 125, 10, 'F1', [0.4, 0.4, 0.5]);

        // Status Badge
        $badgeBg = ($status === 'PAID') ? [0.05, 0.6, 0.3] : [0.8, 0.2, 0.2];
        $pdf->addRect(440, $height - 125, 75, 22, $badgeBg);
        $pdf->addText($status, 458, $height - 118, 10, 'F2', [1.0, 1.0, 1.0]);

        // 3. Customer Info Box
        $pdf->addRect(40, $height - 190, 515, 50, [0.96, 0.97, 0.99], [0.85, 0.88, 0.92]);
        $clientName = $invoiceData['client_name'] ?? 'Sovereign Client';
        $clientEmail = $invoiceData['client_email'] ?? 'client@example.com';
        $pdf->addText("BILLED TO:", 50, $height - 158, 9, 'F2', [0.4, 0.4, 0.5]);
        $pdf->addText($clientName, 50, $height - 173, 11, 'F2', [0.1, 0.1, 0.2]);
        $pdf->addText($clientEmail, 50, $height - 186, 10, 'F1', [0.3, 0.3, 0.4]);

        // 4. Line Items Table Header
        $tableY = $height - 230;
        $pdf->addRect(40, $tableY, 515, 25, [0.08, 0.12, 0.25]);
        $pdf->addText('DESCRIPTION', 50, $tableY + 7, 10, 'F2', [1.0, 1.0, 1.0]);
        $pdf->addText('QTY', 340, $tableY + 7, 10, 'F2', [1.0, 1.0, 1.0]);
        $pdf->addText('UNIT PRICE', 400, $tableY + 7, 10, 'F2', [1.0, 1.0, 1.0]);
        $pdf->addText('AMOUNT', 490, $tableY + 7, 10, 'F2', [1.0, 1.0, 1.0]);

        // 5. Line Items
        $items = $invoiceData['items'] ?? [
            ['description' => 'High-Performance KVM MicroVM (4 vCPU, 8GB RAM, 100GB NVMe)', 'qty' => 1, 'price' => 25.00],
            ['description' => 'Anycast DDoS Shield & Dedicated IPv4 Subnet', 'qty' => 1, 'price' => 5.00],
        ];

        $currentY = $tableY - 25;
        $subtotal = 0.0;
        $currency = $invoiceData['currency'] ?? '$';

        foreach ($items as $item) {
            $qty = (int)($item['qty'] ?? 1);
            $price = (float)($item['price'] ?? 0.0);
            $total = $qty * $price;
            $subtotal += $total;

            $pdf->addRect(40, $currentY, 515, 25, [0.98, 0.98, 0.99], [0.9, 0.9, 0.9]);
            $pdf->addText((string)$item['description'], 50, $currentY + 7, 9, 'F1', [0.1, 0.1, 0.2]);
            $pdf->addText((string)$qty, 345, $currentY + 7, 9, 'F1', [0.1, 0.1, 0.2]);
            $pdf->addText($currency . number_format($price, 2), 405, $currentY + 7, 9, 'F1', [0.1, 0.1, 0.2]);
            $pdf->addText($currency . number_format($total, 2), 495, $currentY + 7, 9, 'F2', [0.1, 0.1, 0.2]);

            $currentY -= 25;
        }

        // 6. Summary Totals
        $tax = $invoiceData['tax'] ?? ($subtotal * 0.05); // 5% VAT
        $grandTotal = $subtotal + $tax;

        $summaryY = $currentY - 20;
        $pdf->addText('Subtotal:', 380, $summaryY, 10, 'F1', [0.3, 0.3, 0.4]);
        $pdf->addText($currency . number_format($subtotal, 2), 490, $summaryY, 10, 'F1', [0.1, 0.1, 0.2]);

        $pdf->addText('VAT / Tax (5%):', 380, $summaryY - 18, 10, 'F1', [0.3, 0.3, 0.4]);
        $pdf->addText($currency . number_format($tax, 2), 490, $summaryY - 18, 10, 'F1', [0.1, 0.1, 0.2]);

        $pdf->addLine(380, $summaryY - 26, 555, $summaryY - 26, [0.7, 0.7, 0.7], 1.0);

        $pdf->addText('Grand Total:', 380, $summaryY - 45, 12, 'F2', [0.0, 0.4, 0.8]);
        $pdf->addText($currency . number_format($grandTotal, 2), 490, $summaryY - 45, 13, 'F2', [0.0, 0.4, 0.8]);

        // 7. Footer Notice
        $pdf->addRect(40, 40, 515, 40, [0.95, 0.96, 0.98]);
        $pdf->addText('Thank you for choosing OSHIM Sovereign Cloud Platform.', 50, 62, 9, 'F2', [0.2, 0.2, 0.3]);
        $pdf->addText('Zero-dependency pure PHP binary PDF generation engine verified.', 50, 48, 8, 'F1', [0.5, 0.5, 0.6]);

        return $pdf;
    }
}
