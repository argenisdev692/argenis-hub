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
        Schema::create('scout_job_postings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('scout_companies')->cascadeOnUpdate()->nullOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('title');
            $table->string('location')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('remote_mode', 16)->default('unknown');
            $table->string('contract_type', 16)->default('unknown');
            $table->string('language', 16)->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('source_url', 2048);
            $table->longText('body_text')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['company_id', 'status']);
        });

        SupabaseRls::protect('scout_job_postings');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_job_postings');
    }
};
