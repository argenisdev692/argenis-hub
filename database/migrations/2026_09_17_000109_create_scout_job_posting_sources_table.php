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
        Schema::create('scout_job_posting_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('posting_id')->constrained('scout_job_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('scout_sources')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['posting_id', 'source_id']);
        });

        SupabaseRls::protect('scout_job_posting_sources');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_job_posting_sources');
    }
};
