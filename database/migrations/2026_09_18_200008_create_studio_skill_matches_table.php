<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_skill_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('score_id')->constrained('studio_scores')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('studio_requirements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('credit_base', 3, 2)->default(0);
            $table->decimal('context_factor', 3, 2)->default(1);
            $table->decimal('position_factor', 3, 2)->default(1);
            $table->decimal('credit_final', 5, 4)->default(0);
            $table->text('evidence_text')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['score_id', 'requirement_id']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_skill_matches');
    }
};
