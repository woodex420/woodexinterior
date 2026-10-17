<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8.2: each payment method maps to the ledger account its money lands in.
 * Seeded with defaults; the owner adjusts them on Accounts → Payment methods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_method_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('method', 40)->unique();
            $table->unsignedInteger('account_id');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_method_accounts');
    }
};
