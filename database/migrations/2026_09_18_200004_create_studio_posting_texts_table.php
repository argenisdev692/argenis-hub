<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_posting_texts', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('ladder_step', 32);
            $table->string('completeness', 16);
            $table->longText('text');
            $table->unsignedInteger('char_count')->default(0);
            $table->string('resolution_note')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['posting_id', 'ladder_step']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_posting_texts');
    }
};
