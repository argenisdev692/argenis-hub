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
        Schema::create('scout_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('type', 32);
            $table->char('country', 2)->nullable();
            $table->string('access_method', 32)->default('api');
            $table->unsignedInteger('frequency_minutes')->default(360);
            $table->unsignedInteger('priority')->default(0);
            $table->string('status', 32)->default('paused');
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_cursor')->nullable();
            $table->dateTime('terms_reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        // A source is never activated without reviewed terms (spec FR-13, T028).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE scout_sources ADD CONSTRAINT ck_scout_sources_terms CHECK (status <> 'active' OR terms_reviewed_at IS NOT NULL)");
        }

        SupabaseRls::protect('scout_sources');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_sources');
    }
};
