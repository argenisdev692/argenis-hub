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
        Schema::create('scout_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('outreach_id')->constrained('scout_outreaches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('hours_per_month')->nullable();
            $table->unsignedBigInteger('hourly_rate_cents')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('status', 16)->default('open');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['outreach_id', 'status']);
        });

        SupabaseRls::protect('scout_opportunities');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_opportunities');
    }
};
