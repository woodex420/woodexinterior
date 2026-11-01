<?php

declare(strict_types=1);

// Isolated native PDF proof only. All identities/items here are synthetic; never run on owner data.
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\CreateManualInvoice;
use App\Domain\Sales\Actions\CreateQuote;
use App\Domain\Sales\Actions\SendQuote;
use App\Domain\Sales\Models\Quote;
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
    throw new RuntimeException('Isolated sandbox only. Never run against owner or production data.');
}
$out = '/tmp/woodex-run/shots/43/';
@mkdir($out, 0755, true);
$owner = User::where('email', 'owner@woodex.test')->firstOrFail();
$client = Contact::create(['name' => 'Quotation Revision QA Client', 'email' => 'revision@example.test', 'city' => 'Lahore']);
$editor = app(QuoteEditor::class);
$proof = [];
foreach ([1, 5, 10, 14, 16, 18, 22, 45] as $count) {
    $quote = app(CreateQuote::class)->handle($client, BusinessLine::Furniture, $owner, title: 'Office Furniture — '.$count.' product proof');
    $section = $quote->sections()->firstOrFail();
    for ($i = 1; $i <= $count; $i++) {
        $data = ['product_name' => 'QA product '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'description' => 'Approved oak finish, assembly and installation at the client site.', 'qty' => '1', 'unit' => 'nos', 'rate_minor' => 1000000];
        $i === 1 ? $editor->updateLine($section->items()->firstOrFail(), $data) : $editor->addLine($section, $data);
    }
    $s = app(QuoteSnapshot::class)->build($quote->refresh());
    $name = '43-furniture-'.$count;
    file_put_contents($out.$name.'.pdf', app(QuotePdf::class)->render($s));
    file_put_contents($out.$name.'.html', app(QuotePdf::class)->html($s));
    $proof['furniture_'.$count] = ['id' => $quote->id, 'total_minor' => $quote->total_minor, 'file' => $name];
    if ($count === 1) {
        app(SendQuote::class)->handle($quote, $owner, 'link', false);
        $version = $quote->versions()->firstOrFail();
        $proof['sent'] = ['id' => $quote->id, 'sha256' => $version->pdf_sha256];
    }
}
$interior = app(CreateQuote::class)->handle($client, BusinessLine::Interior, $owner, title: 'Native Interior block proof');
$editor->updateLine($interior->items()->firstOrFail(), ['description' => 'Interior native block demonstration', 'qty' => '100', 'unit' => 'sft', 'rate_minor' => 125000]);
file_put_contents($out.'43-interior.pdf', app(QuotePdf::class)->render(app(QuoteSnapshot::class)->build($interior->refresh())));
$proof['interior'] = ['id' => $interior->id];
$extended = app(QuoteSnapshot::class)->build(Quote::findOrFail($proof['furniture_5']['id']));
$extended['notes'] = str_repeat('Extended client note: approved finish remains subject to the written specification. ', 100);
$extended['terms'][] = 'Final custom condition must remain visible.';
file_put_contents($out.'43-oversized-closing.pdf', app(QuotePdf::class)->render($extended));
$extended['notes'] = '';
$extended['terms'][] = str_repeat('Long term condition approved. ', 350).'END-LONG-TERM';
file_put_contents($out.'43-long-single-term.pdf', app(QuotePdf::class)->render($extended));
$invoice = app(CreateManualInvoice::class)->handle($owner, ['contact_id' => $client->id, 'business_line' => 'furniture', 'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString(), 'items' => [['product_name' => 'QA invoice desk', 'description' => 'Native invoice regression', 'qty' => '1', 'unit' => 'nos', 'rate_minor' => 4750000]]]);
file_put_contents($out.'43-invoice.pdf', app(InvoicePdf::class)->render(app(InvoiceSnapshot::class)->build($invoice)));
$proof['invoice'] = ['id' => $invoice->id];
file_put_contents($out.'43-proof.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Quotation revision native exports ready.\n";
