<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8.3: persistent, versioned estimates (docs/19 §7). An estimate is a staff document — the
 * inputs, the rate snapshot and the computed range live on the row, so later rate-book changes
 * never silently rewrite it; conversion to a quotation is recorded on both sides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->string('number', 30)->unique();
            $table->string('service_key', 60);
            $table->string('title');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('lead_id')->nullable();
            $table->json('inputs');
            $table->text('assumptions')->nullable();
            $table->text('exclusions')->nullable();
            $table->unsignedInteger('contingency_bp')->default(0);
            $table->json('catalog_lines')->nullable();
            $table->json('computed');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('quote_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'service_key']);
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->unsignedInteger('estimate_id')->nullable()->after('template_id');
            $table->foreign('estimate_id')->references('id')->on('estimates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['estimate_id']);
            $table->dropColumn('estimate_id');
        });

        Schema::dropIfExists('estimates');
    }
};
