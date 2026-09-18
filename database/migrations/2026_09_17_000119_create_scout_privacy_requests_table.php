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
        // No PII by design (spec FR-29): `subject_ref` is a hash, and the
        // activity log for these rows only records type/dates/outcome.
        Schema::create('scout_privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_ref', 64);
            $table->string('request_type', 32);
            $table->dateTime('received_at');
            $table->dateTime('resolved_at')->nullable();
            $table->string('outcome', 32)->nullable();
            $table->timestamps();

            $table->index('subject_ref');
        });

        SupabaseRls::protect('scout_privacy_requests');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_privacy_requests');
    }
};
