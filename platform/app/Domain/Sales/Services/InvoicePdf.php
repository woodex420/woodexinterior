<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/**
 * A4 invoice PDF on the Studio letterhead (INV-001/003), rendered from a snapshot (InvoiceSnapshot) with dompdf.
 * Payment details are printed on the invoice itself: a client who has to ask where to send the money pays late.
 */
final class InvoicePdf
{
    private const float RIGHT_MARGIN = 31.5;

    private const float FOOTER_TEXT_Y = 29.6;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function render(array $snapshot): string
    {
        $pdf = Pdf::loadView('pdf.invoice', ['s' => $snapshot])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isFontSubsettingEnabled', true);

        $pdf->render();

        $pdf->getDomPDF()->getCanvas()->page_script(function (int $page, int $pages, Canvas $canvas, FontMetrics $metrics): void {
            $font = (string) $metrics->getFont('DejaVu Sans');
            $text = "Page {$page} of {$pages}";
            $width = $metrics->getTextWidth($text, $font, 6.0);
            $canvas->text($canvas->get_width() - self::RIGHT_MARGIN - $width, $canvas->get_height() - self::FOOTER_TEXT_Y, $text, $font, 6.0, [0.431, 0.529, 0.675]);
        });

        return $pdf->output();
    }

    public static function filename(Invoice $invoice): string
    {
        return 'invoice-'.str_replace(['/', '\\'], '-', $invoice->number).'.pdf';
    }
}
