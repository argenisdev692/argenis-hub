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
        Schema::create('scout_contact_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('company_id')->constrained('scout_companies')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('channel_type', 32);
            $table->string('url', 2048)->nullable();
            $table->string('generic_email')->nullable();
            $table->json('form_fields')->nullable();
            $table->boolean('has_captcha')->default(false);
            $table->string('audience', 32)->nullable();
            $table->string('evidence_url', 2048)->nullable();
            $table->text('evidence_excerpt')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        SupabaseRls::protect('scout_contact_channels');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_contact_channels');
    }
};
