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
        Schema::create('scout_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('canonical_domain')->unique();
            $table->json('aliases')->nullable();
            $table->string('name');
            // Nullable from day one: manual/imported leads arrive without a
            // country (FR-20) and enrichment backfills it. Never add a later
            // `change()` migration on scout tables with raw partial indexes:
            // sqlite rebuilds the table and drops their predicates.
            $table->char('country', 2)->nullable();
            $table->string('origin', 16);
            $table->string('origin_ref')->nullable();
            $table->string('discovery_wave', 16)->nullable();
            $table->string('company_type', 32)->nullable();
            $table->string('employee_range', 32)->default('unknown');
            $table->unsignedInteger('team_size_observed')->nullable();
            $table->boolean('has_decision_maker')->default(false);
            $table->boolean('needs_research')->default(false);
            $table->string('activity_status', 16)->default('unknown');
            $table->dateTime('last_activity_at')->nullable();
            $table->integer('timezone_overlap_hours')->nullable();
            // Public company data as a legal person (spec FR-38, allowlist).
            $table->string('legal_name')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('registry_info')->nullable();
            $table->string('city')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->json('services')->nullable();
            $table->json('sectors')->nullable();
            $table->json('site_languages')->nullable();
            $table->json('client_companies')->nullable();
            $table->json('public_urls')->nullable();
            $table->json('public_data_evidence')->nullable();
            $table->timestamps();

            $table->index('tax_id');
            $table->index(['country', 'company_type']);
            $table->index(['needs_research', 'created_at']);
            $table->index(['activity_status', 'created_at']);
        });

        SupabaseRls::protect('scout_companies');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_companies');
    }
};
