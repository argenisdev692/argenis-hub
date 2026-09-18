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
        Schema::create('scout_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('source_cv_uuid')->nullable();
            $table->string('cv_hash', 64)->nullable();
            $table->json('confirmed_skills')->nullable();
            $table->json('potential_skills')->nullable();
            $table->json('proof_points')->nullable();
            $table->json('languages')->nullable();
            $table->unsignedInteger('min_rate_cents')->nullable();
            $table->json('target_countries')->nullable();
            $table->json('weights')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_current']);
        });

        DB::statement('CREATE UNIQUE INDEX uq_scout_profiles_current ON scout_profiles (user_id) WHERE is_current = TRUE');

        SupabaseRls::protect('scout_profiles');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_profiles');
    }
};
