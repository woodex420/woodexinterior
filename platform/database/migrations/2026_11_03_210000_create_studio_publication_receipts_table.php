<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A receipt exists iff the transaction containing the public SQL projection committed.
        Schema::create('website_studio_publication_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('manifest_hash', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_studio_publication_receipts');
    }
};
