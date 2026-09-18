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
        Schema::create('scout_decision_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedInteger('sample_size')->default(150);
            $table->unsignedInteger('window_days')->default(56);
            $table->json('thresholds')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->string('result', 32)->nullable();
            $table->date('period_starts_at')->nullable();
            $table->date('period_ends_at')->nullable();
            $table->timestamps();
        });

        SupabaseRls::protect('scout_decision_rules');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_decision_rules');
    }
};
