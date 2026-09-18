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
        Schema::create('scout_budgets', function (Blueprint $table): void {
            $table->id();
            $table->char('period', 7);
            $table->string('category', 32);
            $table->unsignedBigInteger('limit_micros');
            $table->unsignedBigInteger('spent_micros')->default(0);
            $table->timestamps();

            $table->unique(['period', 'category']);
        });

        SupabaseRls::protect('scout_budgets');
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_budgets');
    }
};
