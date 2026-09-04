<?php
declare(strict_types=1);

namespace Oshim\Billing\Pdf;

/**
 * Pure PHP Zero-Dependency PDF 1.4 Binary Generator.
 */
class PdfDocument
{
    private array $objects = [];
    private array $offsets = [];
    private string $contentStream = '';
    private int $pageWidth;
    private int $pageHeight;

    public function __construct(int $pageWidth = 595, int $pageHeight = 842) // A4 (pt)
    {
        $this->pageWidth = $pageWidth;
        $this->pageHeight = $pageHeight;
    }

    public function getPageWidth(): int { return $this->pageWidth; }
    public function getPageHeight(): int { return $this->pageHeight; }

    /**
     * Add text at coordinates (x, y from bottom-left).
     */
    public function addText(
        string $text,
        float $x,
        float $y,
        float $fontSize = 12.0,
        string $font = 'F1',
        array $rgb = [0.0, 0.0, 0.0]
    ): self {
        $escaped = self::escapeString($text);
        [$r, $g, $b] = $rgb;

        $this->contentStream .= sprintf(
            "q %.3f %.3f %.3f rg BT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET Q\n",
            $r, $g, $b, $font, $fontSize, $x, $y, $escaped
        );
        return $this;
    }

    /**
     * Draw a filled or stroked rectangle.
     */
    public function addRect(
        float $x,
        float $y,
        float $w,
        float $h,
        array $fillRgb = [0.9, 0.9, 0.9],
        ?array $strokeRgb = null,
        float $lineWidth = 1.0
    ): self {
        [$fr, $fg, $fb] = $fillRgb;
        $op = 'f';

        $stream = sprintf("q %.3f %.3f %.3f rg ", $fr, $fg, $fb);
        if ($strokeRgb !== null) {
            [$sr, $sg, $sb] = $strokeRgb;
            $stream .= sprintf("%.3f %.3f %.3f RG %.2f w ", $sr, $sg, $sb, $lineWidth);
            $op = 'B';
        }

        $stream .= sprintf("%.2f %.2f %.2f %.2f re %s Q\n", $x, $y, $w, $h, $op);
        $this->contentStream .= $stream;
        return $this;
    }

    /**
     * Draw a line from (x1, y1) to (x2, y2).
     */
    public function addLine(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        array $rgb = [0.5, 0.5, 0.5],
        float $lineWidth = 1.0
    ): self {
        [$r, $g, $b] = $rgb;
        $this->contentStream .= sprintf(
            "q %.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f l S Q\n",
            $r, $g, $b, $lineWidth, $x1, $y1, $x2, $y2
        );
        return $this;
    }

    /**
     * Render the entire document to raw PDF 1.4 binary data.
     */
    public function render(): string
    {
        $this->objects = [];
        $this->offsets = [];

        // 1. Catalog Object
        $this->addObject("<< /Type /Catalog /Pages 2 0 R >>");

        // 2. Pages Object
        $this->addObject("<< /Type /Pages /Kids [3 0 R] /Count 1 >>");

        // 3. Page Object
        $this->addObject(sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents 6 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> >>",
            $this->pageWidth,
            $this->pageHeight
        ));

        // 4. Regular Font (Helvetica)
        $this->addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");

        // 5. Bold Font (Helvetica-Bold)
        $this->addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");

        // 6. Content Stream Object
        $streamLen = strlen($this->contentStream);
        $this->addObject("<< /Length {$streamLen} >>\nstream\n" . $this->contentStream . "endstream");

        // Build Final Binary Output
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

        for ($i = 1; $i <= count($this->objects); $i++) {
            $this->offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n" . $this->objects[$i - 1] . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($this->objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($this->objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $this->offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($this->objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function addObject(string $content): void
    {
        $this->objects[] = $content;
    }

    public static function escapeString(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
