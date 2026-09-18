<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_source_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('studio_sources')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('slug');
            $table->string('company_name')->nullable();
            $table->string('status', 16)->default('found');
            $table->string('ats_kind', 32)->nullable();
            $table->string('discovered_via', 32)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('job_count_last_seen')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['source_id', 'slug']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_source_companies');
    }
};
