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
        Schema::create('scout_search_queries', function (Blueprint $table): void {
            $table->id();
            $table->string('query_hash', 64)->unique();
            $table->string('query_text', 512);
            $table->string('search_depth', 16)->nullable();
            $table->string('purpose', 16);
            $table->string('discovery_wave', 16)->nullable();
            $table->string('family')->nullable();
            $table->char('country', 2)->nullable();
            $table->json('results')->nullable();
            $table->unsignedInteger('new_companies_count')->default(0);
            $table->string('status', 32)->default('ok');
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->timestamps();

            $table->index(['purpose', 'discovery_wave']);
            $table->index(['family', 'created_at']);
        });

        SupabaseRls::protect('scout_search_queries');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_search_queries');
    }
};
