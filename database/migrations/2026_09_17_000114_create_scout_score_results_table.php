<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\LeadScout\Infrastructure\Persistence\Supabase\SupabaseRls;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scout_score_results', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->constrained('scout_companies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('scout_profiles')->cascadeOnUpdate()->nullOnDelete();
            $table->string('rules_version', 32);
            $table->json('subscores');
            $table->unsignedTinyInteger('lead_score');
            $table->unsignedTinyInteger('confidence');
            $table->string('tier', 16);
            $table->string('discard_reason', 32)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        DB::statement('CREATE UNIQUE INDEX uq_scout_score_results_current ON scout_score_results (company_id) WHERE is_current = TRUE');

        SupabaseRls::protect('scout_score_results');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_score_results');
    }
};
