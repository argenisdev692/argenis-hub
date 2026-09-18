<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_scores', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('h', 6, 2)->default(0);
            $table->decimal('s', 6, 2)->default(0);
            $table->decimal('d', 6, 2)->default(0);
            $table->json('h_components')->nullable();
            $table->json('s_components')->nullable();
            $table->json('d_components')->nullable();
            $table->decimal('raw_score', 6, 2)->default(0);
            $table->decimal('total_score', 6, 2)->default(0);
            $table->string('band', 16)->nullable();
            $table->json('caps_applied')->nullable();
            $table->string('cap_reason', 64)->nullable();
            $table->string('embedding_model', 64)->nullable();
            $table->unsignedSmallInteger('embedding_dims')->nullable();
            $table->json('o_components')->nullable();
            $table->decimal('opportunity_static', 5, 4)->nullable();
            $table->decimal('apply_priority', 8, 4)->nullable();
            $table->timestamp('priority_computed_at')->nullable();
            $table->unsignedSmallInteger('rules_version')->default(2);
            $table->timestamp('computed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['posting_id', 'rules_version']);
            $table->index(['deleted_at', 'created_at']);
            $table->index(['total_score', 'band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_scores');
    }
};
