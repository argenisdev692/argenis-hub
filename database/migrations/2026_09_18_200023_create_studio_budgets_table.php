<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('period', 7);
            $table->string('category', 16);
            $table->unsignedBigInteger('limit_micros')->default(0);
            $table->unsignedBigInteger('spent_micros')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'period', 'category']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_budgets');
    }
};
