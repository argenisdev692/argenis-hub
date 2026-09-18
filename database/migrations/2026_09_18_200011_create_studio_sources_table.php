<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('name');
            $table->string('endpoint', 2048)->nullable();
            $table->string('auth_kind', 32)->default('none');
            $table->unsignedTinyInteger('tier')->default(4);
            $table->string('layer', 16)->default('expansion');
            $table->boolean('attribution_required')->default(false);
            $table->string('attribution_text')->nullable();
            $table->boolean('redistribution_allowed')->default(true);
            $table->unsignedSmallInteger('publish_delay_hours')->default(0);
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(10);
            $table->string('status', 16)->default('active');
            $table->timestamp('health_checked_at')->nullable();
            $table->json('gate_tags')->nullable();
            $table->unsignedInteger('resolution_priority')->default(100);
            $table->boolean('supplies_full_text')->default(false);
            $table->boolean('company_scoped')->default(false);
            $table->string('ats_kind', 32)->nullable();
            $table->string('endpoint_template', 2048)->nullable();
            $table->boolean('supports_since_filter')->default(false);
            $table->boolean('requires_api_key')->default(false);
            $table->string('access_mode', 16)->default('link_only');
            $table->unsignedTinyInteger('resolution_tier')->default(4);
            $table->string('usage_restriction', 32)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['deleted_at', 'created_at']);
            $table->index(['status', 'resolution_priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_sources');
    }
};
