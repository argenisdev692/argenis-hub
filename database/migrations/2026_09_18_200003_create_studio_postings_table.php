<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_postings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('studio_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('canonical_url', 2048);
            $table->char('url_hash', 64)->unique();
            $table->char('fingerprint', 64)->nullable();
            $table->string('source', 64)->nullable();
            $table->string('employer_name')->nullable();
            $table->string('title');
            $table->string('location_text')->nullable();
            $table->string('remote_scope', 32)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_at_source', 32)->nullable();
            $table->string('salary_text')->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('attribution')->nullable();
            $table->string('status', 32)->default('new');
            $table->string('discovery_channel', 32)->nullable();
            $table->string('apply_destination', 32)->nullable();
            $table->string('ats_kind', 32)->nullable();
            $table->unsignedBigInteger('preferred_source_id')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_alive_check_at')->nullable();
            $table->string('alive_check_method', 32)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->decimal('d_disc', 5, 4)->nullable();
            $table->json('d_disc_components')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['deleted_at', 'created_at']);
            $table->index(['user_id', 'profile_id']);
            $table->index(['profile_id', 'status']);
            $table->index(['fingerprint']);
            $table->index(['is_expired', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_postings');
    }
};
