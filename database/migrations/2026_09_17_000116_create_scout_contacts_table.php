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
        Schema::create('scout_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->constrained('scout_companies')->cascadeOnUpdate()->cascadeOnDelete();
            // Nullable from day one (FR-27 anonymization nulls them).
            // NOTE: never add a later `change()` migration on this table:
            // sqlite rebuilds the table and drops the partial unique below.
            $table->string('full_name')->nullable();
            $table->string('role_title')->nullable();
            $table->string('role_category', 32);
            $table->boolean('is_primary')->default(false);
            $table->string('published_email')->nullable();
            $table->string('email_kind', 16)->nullable();
            $table->string('public_profile_url', 2048)->nullable();
            $table->string('source', 16);
            $table->string('evidence_url', 2048)->nullable();
            $table->text('evidence_excerpt')->nullable();
            $table->dateTime('evidence_captured_at')->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->dateTime('contact_deadline_at')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('last_verified_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'anonymized_at']);
        });

        DB::statement('CREATE UNIQUE INDEX uq_scout_contacts_primary ON scout_contacts (company_id) WHERE is_primary = TRUE AND anonymized_at IS NULL');

        SupabaseRls::protect('scout_contacts');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_contacts');
    }
};
