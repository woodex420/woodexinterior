<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/**
 * A4 quotation PDF on the Studio letterhead (QUO-014), rendered from a snapshot (QuoteSnapshot) with dompdf.
 * Drafts carry a "DRAFT" watermark; sent versions are rendered once at send time and stored with their SHA-256.
 */
final class QuotePdf
{
    /** Position of "Page X of Y" in points (CSS px × 0.75): the template's right margin, and the footer text line. */
    private const float RIGHT_MARGIN = 31.5;

    private const float FOOTER_TEXT_Y = 29.6; // from the bottom edge: level with the footer text on the left

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function render(array $snapshot, bool $draft = false): string
    {
        $pdf = Pdf::loadView('pdf.quote', ['s' => $snapshot, 'draft' => $draft])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans')
            // laravel-dompdf turns subsetting off by default: whole DejaVu fonts made a one-page quotation 1.2 MB, too
            // heavy for WhatsApp. Only the glyphs used are embedded now.
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();

        // "Page X of Y", right-aligned in the footer: CSS counters in dompdf have no page total.
        $pdf->getDomPDF()->getCanvas()->page_script(function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics): void {
            $font = (string) $metrics->getFont('DejaVu Sans');
            $text = "Page {$page} of {$pages}";
            $width = $metrics->getTextWidth($text, $font, 6.0);
            $canvas->text($canvas->get_width() - self::RIGHT_MARGIN - $width, $canvas->get_height() - self::FOOTER_TEXT_Y, $text, $font, 6.0, [0.431, 0.529, 0.675]);
        });

        return $pdf->output();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function filename(array $snapshot): string
    {
        return 'Woodex-Quotation-'.str_replace([' ', '·'], ['-', ''], (string) $snapshot['label']).'.pdf';
    }
}
