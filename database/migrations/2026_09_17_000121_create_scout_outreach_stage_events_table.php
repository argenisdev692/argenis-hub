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
        Schema::create('scout_outreach_stage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outreach_id')->constrained('scout_outreaches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('from_stage', 32)->nullable();
            $table->string('to_stage', 32);
            $table->string('reply_outcome', 32)->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['outreach_id', 'created_at']);
        });

        SupabaseRls::protect('scout_outreach_stage_events');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_outreach_stage_events');
    }
};
