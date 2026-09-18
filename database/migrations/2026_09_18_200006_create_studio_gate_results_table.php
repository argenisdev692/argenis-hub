<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_gate_results', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('posting_id')->constrained('studio_postings')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('gate_code', 8);
            $table->boolean('passed')->default(false);
            $table->string('reason_code', 64)->nullable();
            $table->text('detail')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['posting_id', 'gate_code']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_gate_results');
    }
};
