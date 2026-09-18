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
        Schema::create('scout_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose', 32)->unique();
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('fallback_provider', 32)->nullable();
            $table->string('fallback_model', 64)->nullable();
            $table->timestamps();
        });

        SupabaseRls::protect('scout_ai_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_ai_settings');
    }
};
