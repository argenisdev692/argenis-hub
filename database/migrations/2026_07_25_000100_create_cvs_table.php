<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 — CV upload aggregate. AI/RAG/jobs tables land in Module 2 later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cvs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->string('niche', 32); // fullstack | other
            $table->boolean('is_primary')->default(false);
            $table->string('file_path');
            $table->string('file_type', 8); // pdf | md
            $table->string('original_filename');
            $table->longText('raw_text')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('deleted_at', 'idx_cvs_deleted_at');
            $table->index(['deleted_at', 'created_at']);
            $table->index(['user_id', 'is_primary']);
            $table->index(['niche', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cvs');
    }
};
