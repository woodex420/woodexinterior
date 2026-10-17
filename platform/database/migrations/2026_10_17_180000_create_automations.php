<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8.5: the owner's on/off switch per automation. Rows only exist once someone toggles —
 * the catalog lives in code (Automation::CATALOG) and a missing row means "enabled", so a
 * fresh install or an un-seeded update is correct without any seeding step.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Resumable on MySQL (non-transactional DDL) — see the P8.4 production incident.
        if (Schema::hasTable('automations')) {
            return;
        }

        Schema::create('automations', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedInteger('toggled_by')->nullable();
            $table->timestamp('toggled_at')->nullable();
            $table->timestamps();

            $table->foreign('toggled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
