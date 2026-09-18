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
        Schema::create('scout_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('canonical_domain')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('name')->nullable();
            $table->string('source', 32);
            $table->string('reason');
            $table->string('list_period', 16)->nullable();
            $table->timestamps();

            $table->index('tax_id');
        });

        DB::statement('CREATE UNIQUE INDEX uq_scout_suppressions_domain ON scout_suppressions (canonical_domain) WHERE canonical_domain IS NOT NULL');

        SupabaseRls::protect('scout_suppressions');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_suppressions');
    }
};
