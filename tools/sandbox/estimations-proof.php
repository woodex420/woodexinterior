<?php

declare(strict_types=1);

use App\Domain\Crm\Actions\CaptureLead;
use App\Domain\Crm\Data\LeadInput;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\SaveEstimate;
use App\Domain\Sales\Models\Estimate;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$base = '/tmp/woodex-w-qa/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base || config('database.connections.sqlite.database') !== $base.'/database/database.sqlite') {
    throw new RuntimeException('Isolated sandbox only.');
}
if (($argv[1] ?? '') === 'parity') {
    $out = [];
    foreach (App\Domain\Sales\Models\EstimatorRate::query()->where('is_active', true)->orderBy('position')->get() as $rate) {
        foreach (['essential', 'standard', 'premium'] as $finish) {
            $quantity = in_array($rate->unit, ['view', 'views'], true) ? '10' : '100';
            $computed = app(App\Domain\Sales\Actions\ComputeEstimate::class)->handle($rate, $quantity, $finish, [], 0);
            $out[] = ['service' => $rate->key, 'finish' => $finish, 'quantity' => $quantity, 'low' => $computed['range_low_minor'], 'high' => $computed['range_high_minor'], 'selected' => $computed['headline_minor']];
        }
    }
    echo json_encode($out), "\n";
    exit;
}
$by = User::where('email', 'owner@woodex.test')->firstOrFail();
$contact = Contact::create(['name' => 'W QA Saved Client', 'phone' => '+923000009801', 'owner_id' => $by->id]);
$estimate = app(SaveEstimate::class)->handle(['title' => 'W QA saved snapshot', 'contact_id' => $contact->id, 'service_key' => 'interior-design', 'quantity' => '10', 'finish' => 'standard', 'contingency_bp' => 0], $by);
$lead = app(CaptureLead::class)->handle(new LeadInput(name: 'W QA Furniture Client', phone: '+923000009802', email: null, source: LeadSource::Estimator, businessLine: BusinessLine::Furniture, service: 'furniture', areaSqft: 10, ownerId: $by->id, meta: ['fields' => ['service' => 'furniture', 'area' => '10', 'finish' => 'standard']]))->lead;
$furniture = Estimate::where('lead_id', $lead->id)->sole();
$unknown = app(CaptureLead::class)->handle(new LeadInput(name: 'W QA Unpriced Client', phone: '+923000009803', email: null, source: LeadSource::Estimator, ownerId: $by->id, meta: ['fields' => ['service' => 'unknown-audit-service', 'area' => '10', 'finish' => 'standard']]))->lead;
echo json_encode(['estimate_id' => $estimate->id, 'furniture_id' => $furniture->id, 'lead_id' => $lead->id, 'unknown_id' => $unknown->id, 'saved_total_minor' => $estimate->computed['total_minor']]), "\n";
