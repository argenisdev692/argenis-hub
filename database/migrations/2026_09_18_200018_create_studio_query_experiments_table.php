<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_query_experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('portal');
            $table->foreignId('template_id')->nullable()->constrained('studio_query_templates')->cascadeOnUpdate()->nullOnDelete();
            $table->string('locale', 16)->nullable();
            $table->unsignedInteger('candidates')->default(0);
            $table->decimal('gate_pass_rate', 5, 4)->default(0);
            $table->unsignedInteger('unique_after_dedup')->default(0);
            $table->unsignedInteger('scored_gte_70')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->timestamp('ran_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['portal', 'locale']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_query_experiments');
    }
};
