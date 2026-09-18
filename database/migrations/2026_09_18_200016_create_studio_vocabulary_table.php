<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_vocabulary', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('studio_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('term');
            $table->string('kind', 16);
            $table->string('language', 8)->nullable();
            $table->decimal('weight', 5, 4)->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['profile_id', 'term', 'language']);
            $table->index(['deleted_at', 'created_at']);
            $table->index(['term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_vocabulary');
    }
};
