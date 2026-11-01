<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\CreateFurnitureInvoice;
use App\Domain\Sales\Actions\CreateQuote;
use App\Domain\Sales\Actions\SendQuote;
use App\Domain\Sales\Enums\DiscountType;
use App\Domain\Sales\Services\InvoicePdf;
use App\Domain\Sales\Services\InvoiceSnapshot;
use App\Domain\Sales\Services\QuoteEditor;
use App\Domain\Sales\Services\QuotePdf;
use App\Domain\Sales\Services\QuoteSnapshot;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$base = '/tmp/woodex-furniture-qa/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base || config('database.connections.sqlite.database') !== $base.'/database/database.sqlite') {
    throw new RuntimeException('Sandbox-only proof.');
}

$by = User::where('email', 'owner@woodex.test')->firstOrFail();
$contact = Contact::create(['name' => 'Furniture QA Client', 'phone' => '+923000000001', 'city' => 'Lahore']);
$q = app(CreateQuote::class)->handle($contact, BusinessLine::Furniture, $by, title: 'Furniture QA proof');
$e = app(QuoteEditor::class);
$line = $q->items()->firstOrFail();
$e->updateLine($line, ['product_name' => 'Three-seat sofa', 'description' => 'Solid wood frame; MoltyFoam; approved fabric.', 'qty' => '1', 'unit' => 'nos', 'rate_minor' => 24000000]);
$sec = $q->sections()->firstOrFail();
$e->addLine($sec, ['product_name' => 'Mesh chair', 'description' => 'Imported black mesh, adjustable height and armrests.', 'qty' => '5', 'unit' => 'nos', 'rate_minor' => 2000000]);
$e->addLine($sec, ['product_name' => 'Workstation', 'description' => 'Al-Noor tactile top; powder-coated MS frame; imported hardware.', 'qty' => '3', 'unit' => 'nos', 'rate_minor' => 5000000]);
$e->updateFurnitureText($q, ['signatories_text' => "QA Director | Director\nQA Manager | Marketing Manager", 'delivery_text' => '8–10 working days', 'tax_note' => 'Prices exclude applicable taxes unless stated.']);
$s = app(QuoteSnapshot::class)->build($q->refresh());
file_put_contents('/tmp/woodex-run/shots/furniture-quotation.pdf', app(QuotePdf::class)->render($s));
app(SendQuote::class)->handle($q, $by);
$inv = app(CreateFurnitureInvoice::class)->handle($q->refresh(), $by);
file_put_contents('/tmp/woodex-run/shots/furniture-invoice.pdf', app(InvoicePdf::class)->render(app(InvoiceSnapshot::class)->build($inv)));
// Pagination stress: data stays in this exported snapshot, never in the database.
$long = $s;
$long['sections'][0]['items'] = array_fill(0, 45, $s['sections'][0]['items'][2]);
foreach ($long['sections'][0]['items'] as $i => &$item) {
    $item['product_name'] = 'Pagination product '.($i + 1);
    $item['description'] = 'Pagination description '.($i + 1).': '.str_repeat('Durable laminate and powder-coated frame; approved finish. ', 3);
}
unset($item);
file_put_contents('/tmp/woodex-run/shots/furniture-long.pdf', app(QuotePdf::class)->render($long));
$small = $s;
$small['sections'][0]['items'] = [$s['sections'][0]['items'][0]];
$small['sections'][0]['items'][0]['rate_minor'] = 4750000;
$small['sections'][0]['items'][0]['line_total_minor'] = 4750000;
$small['totals'] = [...$small['totals'], 'subtotal_minor' => 4750000, 'total_minor' => 4750000, 'advance_bp' => 5000, 'advance_minor' => 2375000, 'after_advance_minor' => 2375000];
file_put_contents('/tmp/woodex-run/shots/furniture-47500.pdf', app(QuotePdf::class)->render($small));
file_put_contents('/tmp/woodex-run/shots/furniture-proof.json', json_encode(['quote_id' => $q->id, 'invoice_id' => $inv->id, 'contact_id' => $contact->id]));
echo json_encode(['quote_id' => $q->id, 'invoice_id' => $inv->id, 'total' => $q->total_minor]),"\n";

// Current HTML-reference layout, using real native values rather than the attachment's JS totals.
$reference = app(CreateQuote::class)->handle($contact, BusinessLine::Furniture, $by, title: 'Furniture reference layout proof');
$section = $reference->sections()->firstOrFail();
$e->updateLine($reference->items()->firstOrFail(), ['product_name' => 'Office desk', 'description' => 'Solid oak veneer with cable management.', 'qty' => '2', 'rate_minor' => 8500000, 'unit' => 'nos']);
foreach ([['Office chair', '2', 4250000], ['Conference table', '1', 14500000], ['Storage cabinet', '3', 5500000], ['Installation', '1', 3500000]] as [$product, $quantity, $rate]) {
    $e->addLine($section, ['product_name' => $product, 'description' => $product.' in the approved finish with matching hardware.', 'qty' => $quantity, 'rate_minor' => $rate, 'unit' => 'nos']);
}
$e->updateDetails($reference, ['discount_type' => DiscountType::Percent, 'discount_value' => 500, 'tax_bp' => 1300, 'other_charges_minor' => 1500000, 'site' => 'Lahore QA site']);
$e->updateFurnitureText($reference, ['signatories_text' => '', 'delivery_text' => 'As agreed after design approval.']);
$referenceSnapshot = app(QuoteSnapshot::class)->build($reference->refresh());
file_put_contents('/tmp/woodex-run/shots/furniture-reference.pdf', app(QuotePdf::class)->render($referenceSnapshot));
file_put_contents('/tmp/woodex-run/shots/furniture-reference.html', app(QuotePdf::class)->html($referenceSnapshot));
