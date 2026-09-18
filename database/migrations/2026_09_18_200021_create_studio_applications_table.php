<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_version_id')->nullable();
            $table->unsignedBigInteger('export_id')->nullable();
            $table->string('status', 32)->default('saved');
            $table->timestamp('applied_at')->nullable();
            $table->string('outcome', 16)->default('unknown');
            $table->timestamp('outcome_at')->nullable();
            $table->text('outcome_note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['posting_id', 'outcome']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_applications');
    }
};
