<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_posting_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('studio_sources')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['posting_id', 'source_id']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_posting_sources');
    }
};
