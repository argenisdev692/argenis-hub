<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_posting_sightings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('studio_sources')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamp('observed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['posting_id', 'observed_at']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_posting_sightings');
    }
};
