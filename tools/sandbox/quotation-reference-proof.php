<?php

declare(strict_types=1);

// Isolated demo proof only. The browser runner restores this independent SQLite after all checks.
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\CreateQuote;
use App\Domain\Sales\Services\DocumentStyles;
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
$out = '/tmp/woodex-run/shots/';
$owner = User::where('email', 'owner@woodex.test')->firstOrFail();
$contact = Contact::create(['name' => 'Quotation Reference QA Client', 'email' => 'layout@example.test', 'city' => 'Lahore']);
$editor = app(QuoteEditor::class);
$interior = app(CreateQuote::class)->handle($contact, BusinessLine::Interior, $owner, title: 'Residence Interior Renovation & Turnkey');
$editor->updateDetails($interior, ['site' => 'Sample residence, Lahore', 'type' => 'Renovation']);
$groups = [
    ['Demolition & civil works', [['Existing partitions and damaged flooring removal', '1250', 6000, 'sft'], ['Sanitary fixtures and plumbing point removal', '1', 7000000, 'job']]],
    ['Architectural & woodwork', [['Brick masonry and plaster levelling', '340', 45000, 'sft'], ['Steel support for wall openings', '2', 4250000, 'job'], ['Waterproof screed and self-levelling sub-base', '850', 9500, 'sft'], ['Porcelain floor tile installation and grouting', '1150', 22000, 'sft'], ['Gypsum suspended ceiling and cove framing', '1400', 14500, 'sft'], ['Solid wood door frames and architraves', '6', 2400000, 'nos']]],
    ['Electrical & MEP works', [['Concealed wiring and distribution boxes', '1', 11500000, 'job'], ['Architectural magnetic track lighting points', '28', 250000, 'nos'], ['Concealed sanitary plumbing and pressure testing', '1', 7500000, 'job']]],
    ['Finishing & paint', [['Surface preparation and washable emulsion paint', '2200', 7500, 'sft']]],
];
foreach ($groups as $i => [$name, $lines]) {
    $section = $i === 0 ? $interior->sections()->firstOrFail() : $editor->addSection($interior, $name);
    $editor->updateSection($section, ['name' => $name, 'note' => 'Section '.($i + 1)]);
    foreach ($lines as $j => [$description, $quantity, $rate, $unit]) {
        $attributes = ['description' => $description, 'qty' => $quantity, 'rate_minor' => $rate, 'unit' => $unit];
        $j === 0 ? $editor->updateLine($section->items()->firstOrFail(), $attributes) : $editor->addLine($section, $attributes);
    }
}
$full = app(QuoteSnapshot::class)->build($interior->refresh());
file_put_contents($out.'42-interior-full.pdf', app(QuotePdf::class)->render($full));
$blocks = array_map(static fn (array $b): array => ['key' => $b['key'], 'visible' => in_array($b['key'], ['summary', 'totals', 'boq', 'signatures'], true)], DocumentStyles::blocks('quote', null));
$editor->updateDocumentDesign($interior, ['quote_blocks' => $blocks]);
$snapshot = app(QuoteSnapshot::class)->build($interior->refresh());
file_put_contents($out.'42-interior-reference.pdf', app(QuotePdf::class)->render($snapshot));
file_put_contents($out.'42-interior-reference.html', app(QuotePdf::class)->html($snapshot));
$longQuote = app(CreateQuote::class)->handle($contact, BusinessLine::Interior, $owner, title: 'Pagination proof');
$longSection = $longQuote->sections()->firstOrFail();
for ($i = 1; $i <= 55; $i++) {
    $attributes = ['description' => 'Pagination work '.$i.': '.str_repeat('Reviewed finish and installation specification. ', 3), 'qty' => '1', 'rate_minor' => 10000, 'unit' => 'job'];
    $i === 1 ? $editor->updateLine($longSection->items()->firstOrFail(), $attributes) : $editor->addLine($longSection, $attributes);
}
$editor->updateDocumentDesign($longQuote, ['quote_blocks' => $blocks]);
file_put_contents($out.'42-interior-long.pdf', app(QuotePdf::class)->render(app(QuoteSnapshot::class)->build($longQuote->refresh())));
$furniture = app(CreateQuote::class)->handle($contact, BusinessLine::Furniture, $owner, title: 'Office Furniture Supply & Installation');
$section = $furniture->sections()->firstOrFail();
foreach ([['Office desk', 'Solid oak veneer with cable management.', '2', 8500000], ['Office chair', 'Mesh back, adjustable arms and seat height.', '2', 4250000], ['Conference table', 'Approved laminate top and powder-coated base.', '1', 14500000], ['Storage cabinet', 'Soft-close hinges and matching approved finish.', '3', 5500000], ['Installation', 'Assembly and installation at the approved site.', '1', 3500000]] as $i => [$name, $description, $quantity, $rate]) {
    $attributes = ['product_name' => $name, 'description' => $description, 'qty' => $quantity, 'rate_minor' => $rate, 'unit' => 'nos'];
    $i === 0 ? $editor->updateLine($section->items()->firstOrFail(), $attributes) : $editor->addLine($section, $attributes);
}
$editor->updateDetails($furniture, ['site' => 'Sample office, Lahore']);
$editor->updateFurnitureText($furniture, ['signatories_text' => "Imtiaz Ahmad | Director\nNabeel Afzal | Marketing Manager"]);
$fs = app(QuoteSnapshot::class)->build($furniture->refresh());
file_put_contents($out.'42-furniture-reference.pdf', app(QuotePdf::class)->render($fs));
file_put_contents($out.'42-furniture-reference.html', app(QuotePdf::class)->html($fs));
file_put_contents($out.'42-reference-proof.json', json_encode(['interior' => $interior->id, 'furniture' => $furniture->id, 'contact' => $contact->id, 'interior_item' => $interior->items()->firstOrFail()->id, 'interior_total' => $interior->total_minor, 'furniture_total' => $furniture->total_minor], JSON_PRETTY_PRINT));
echo "Native Interior and Furniture proof exports ready.\n";
