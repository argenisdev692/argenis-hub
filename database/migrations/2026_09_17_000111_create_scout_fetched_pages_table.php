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
        Schema::create('scout_fetched_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->constrained('scout_companies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('page_type', 16)->nullable();
            $table->longText('content_markdown')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->dateTime('content_pruned_at')->nullable();
            $table->json('forms_summary')->nullable();
            $table->dateTime('fetched_at');
            $table->timestamps();

            $table->unique(['company_id', 'url']);
        });

        SupabaseRls::protect('scout_fetched_pages');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_fetched_pages');
    }
};
