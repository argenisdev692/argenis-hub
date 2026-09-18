<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_provider_calls', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('run_id')->nullable();
            $table->string('category', 16);
            $table->string('provider', 64);
            $table->string('operation', 64)->nullable();
            $table->string('purpose', 64)->nullable();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_input_tokens')->default(0);
            $table->unsignedInteger('cache_creation_input_tokens')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->boolean('succeeded')->default(true);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['run_id', 'category']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_provider_calls');
    }
};
