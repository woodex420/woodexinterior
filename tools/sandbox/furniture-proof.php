<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\CreateFurnitureInvoice;
use App\Domain\Sales\Actions\CreateQuote;
use App\Domain\Sales\Actions\SendQuote;
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
$e->updateFurnitureText($q, ['delivery_text' => '8–10 working days', 'tax_note' => 'Prices exclude applicable taxes unless stated.']);
$s = app(QuoteSnapshot::class)->build($q->refresh());
file_put_contents('/tmp/woodex-run/shots/furniture-quotation.pdf', app(QuotePdf::class)->render($s));
app(SendQuote::class)->handle($q, $by);
$inv = app(CreateFurnitureInvoice::class)->handle($q->refresh(), $by);
file_put_contents('/tmp/woodex-run/shots/furniture-invoice.pdf', app(InvoicePdf::class)->render(app(InvoiceSnapshot::class)->build($inv)));
// Pagination stress: data stays in this exported snapshot, never in the database.
$long = $s;
$long['sections'][0]['items'] = array_fill(0, 45, $s['sections'][0]['items'][2]);
file_put_contents('/tmp/woodex-run/shots/furniture-long.pdf', app(QuotePdf::class)->render($long));
file_put_contents('/tmp/woodex-run/shots/furniture-proof.json', json_encode(['quote_id' => $q->id, 'invoice_id' => $inv->id, 'contact_id' => $contact->id]));
echo json_encode(['quote_id' => $q->id, 'invoice_id' => $inv->id, 'total' => $q->total_minor]),"\n";
