<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_channel_baselines', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('bucket_kind', 32);
            $table->string('bucket', 64);
            $table->decimal('policy_value', 5, 4);
            $table->string('grade', 4);
            $table->string('source')->nullable();
            $table->unsignedInteger('positives')->default(0);
            $table->unsignedInteger('trials')->default(0);
            $table->timestamp('applied_from')->nullable();
            $table->timestamp('gate_passed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'bucket_kind', 'bucket']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_channel_baselines');
    }
};
