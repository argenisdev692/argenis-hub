<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\LeadScout\Infrastructure\Persistence\Supabase\SupabaseRls;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_fetch_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('scout_companies')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('scout_sources')->cascadeOnUpdate()->nullOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('method', 16);
            $table->string('status', 32);
            $table->unsignedInteger('attempts')->default(1);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        SupabaseRls::protect('scout_fetch_attempts');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_fetch_attempts');
    }
};
