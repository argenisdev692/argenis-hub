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
        Schema::create('scout_score_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('score_result_id')->constrained('scout_score_results')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('signal_id')->nullable()->constrained('scout_signals')->cascadeOnUpdate()->nullOnDelete();
            $table->integer('points');
            $table->string('explanation', 512);
            $table->timestamps();

            $table->index('score_result_id');
        });

        SupabaseRls::protect('scout_score_reasons');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_score_reasons');
    }
};
