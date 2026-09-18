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
        Schema::create('scout_outreaches', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->constrained('scout_companies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('scout_contacts')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('contact_channel_id')->nullable()->constrained('scout_contact_channels')->cascadeOnUpdate()->nullOnDelete();
            $table->string('send_medium', 32)->nullable();
            $table->string('outreach_kind', 32)->nullable();
            $table->string('template_key')->nullable();
            $table->unsignedInteger('template_version')->nullable();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('sender_kind', 32)->nullable();
            $table->string('stage', 32)->default('draft');
            $table->longText('draft_body')->nullable();
            $table->string('variant', 32)->nullable();
            $table->string('signal_used')->nullable();
            $table->string('ai_provider', 32)->nullable();
            $table->string('ai_model', 64)->nullable();
            $table->string('channel_warning')->nullable();
            $table->string('legal_rule_status', 32)->nullable();
            $table->dateTime('legal_ack_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('stage_changed_at')->nullable();
            $table->string('reply_outcome', 32)->nullable();
            $table->dateTime('replied_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage']);
            $table->index(['stage', 'sent_at']);
        });

        // A `sent` outreach always records how, when, by whom and from which
        // mailbox (spec FR-41, T012). Validation layers enforce it too; this
        // CHECK is the last line of defence on PostgreSQL.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE scout_outreaches ADD CONSTRAINT ck_scout_outreaches_sent CHECK (stage <> 'sent' OR (send_medium IS NOT NULL AND sent_at IS NOT NULL AND operator_id IS NOT NULL AND sender_kind IS NOT NULL))");
        }

        SupabaseRls::protect('scout_outreaches');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_outreaches');
    }
};
