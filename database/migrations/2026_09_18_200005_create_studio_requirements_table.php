<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('canonical_name');
            $table->text('raw_text')->nullable();
            $table->string('tag', 16);
            $table->string('nature', 16);
            $table->decimal('weight', 5, 4)->default(0);
            $table->decimal('normalized_weight', 8, 6)->default(0);
            $table->char('source_text_hash', 64)->nullable();
            $table->string('extracted_by_provider', 64)->nullable();
            $table->string('extracted_by_model', 64)->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['posting_id', 'tag']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_requirements');
    }
};
