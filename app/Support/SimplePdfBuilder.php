<?php

namespace App\Support;

class SimplePdfBuilder
{
    private array $lines = [];

    public function addLine(string $line = ''): self
    {
        $this->lines[] = $line;

        return $this;
    }

    public function addBlankLine(): self
    {
        return $this->addLine('');
    }

    public function build(): string
    {
        $contentStream = $this->buildContentStream();

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
            4 => "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n";
        $pdf .= sprintf("%010d 65535 f \n", 0);
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer << /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPosition}\n%%EOF";

        return $pdf;
    }

    private function buildContentStream(): string
    {
        $content = "BT\n/F1 12 Tf\n";
        $y = 800;

        foreach ($this->lines as $line) {
            $escaped = $this->escapeText($line);
            $content .= sprintf("1 0 0 1 40 %.2f Tm (%s) Tj\n", $y, $escaped);
            $y -= 16;
        }

        $content .= "ET";

        return $content;
    }

    private function escapeText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            mb_convert_encoding($text, 'UTF-8', 'UTF-8')
        );
    }
}
