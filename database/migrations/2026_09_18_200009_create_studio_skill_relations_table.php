<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_skill_relations', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('from_skill');
            $table->string('to_skill');
            $table->string('kind', 16);
            $table->string('origin', 32)->default('seed');
            $table->string('status', 16)->default('confirmed');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'from_skill', 'to_skill', 'kind'], 'studio_skill_rel_unique');
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_skill_relations');
    }
};
