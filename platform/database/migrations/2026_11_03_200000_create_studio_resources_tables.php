<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate from page-builder PageTemplate and individual ContentEntry contracts. No backfill.
        Schema::create('website_studio_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 20);
            $table->string('scope', 10);
            $table->unsignedBigInteger('revision')->default(1);
            $table->uuid('token');
            $table->json('draft_payload')->nullable();
            $table->json('published_payload')->nullable();
            $table->unsignedBigInteger('published_revision')->nullable();
            $table->char('published_hash', 64)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'scope', 'id']);
        });

        Schema::create('website_studio_resource_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_id')->constrained('website_studio_resources')->restrictOnDelete();
            $table->unsignedBigInteger('revision');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['resource_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_studio_resource_versions');
        Schema::dropIfExists('website_studio_resources');
    }
};
