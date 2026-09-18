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
        Schema::create('scout_signals', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->constrained('scout_companies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('dimension', 32);
            $table->string('signal_key');
            $table->text('value_text')->nullable();
            $table->string('nature', 16);
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('evidence_url', 2048)->nullable();
            $table->text('evidence_excerpt')->nullable();
            $table->dateTime('captured_at');
            $table->string('extraction_method', 16);
            $table->string('ai_provider', 32)->nullable();
            $table->string('ai_model', 64)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'dimension']);
            $table->index(['signal_key', 'nature']);
        });

        SupabaseRls::protect('scout_signals');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_signals');
    }
};
