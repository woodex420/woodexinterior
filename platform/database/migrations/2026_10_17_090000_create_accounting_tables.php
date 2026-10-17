<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9-A: the accounting spine (docs/17 §4).
 *
 * Double entry only: every document (invoice, payment, expense, bill) posts one balanced journal entry through
 * App\Domain\Accounting\Services\PostJournalEntry. Amounts are integer paisa, like the rest of the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('type', 20)->index();        // asset | liability | equity | income | expense
            $table->string('subtype', 30)->default('other');
            $table->string('description', 255)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_system')->default(false); // the posting rules depend on it: no delete, no rename
            $table->boolean('is_active')->default(true);
            $table->char('currency', 3)->default('PKR');
            $table->bigInteger('opening_minor')->default(0);
            $table->date('opening_on')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 12)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['starts_on', 'ends_on']);
        });

        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->integer('rate_bp')->default(0);       // 1800 = 18%
            $table->string('kind', 10)->default('output'); // output | input | exempt
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->date('date')->index();
            $table->string('memo', 255);
            $table->string('status', 12)->default('posted')->index();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 40)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One posting per document event: retrying "verify payment 42" can never double the ledger.
            $table->unique(['source_type', 'source_id', 'source_event']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->bigInteger('debit_minor')->default(0);
            $table->bigInteger('credit_minor')->default(0);
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('business_line', 20)->nullable();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('memo', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('tax_codes');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounts');
    }
};
