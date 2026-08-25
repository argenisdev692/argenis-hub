<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session / device tracking (spec 001 FR-14, FR-15).
 *
 * Laravel's own `sessions` table only carries `user_id` + `ip_address` +
 * `user_agent` under the `database` session driver; production runs Redis, where
 * that data does not exist. This dedicated table makes the sessions list and the
 * new-device alert work identically under EVERY session driver (research R5).
 *
 * Deliberately WITHOUT SoftDeletes: `revoked_at` already is the lifecycle marker
 * for this aggregate, and a second tombstone column would only duplicate it.
 * Rows are pruned by MassPrunable instead (see AuthSessionEloquentModel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('device_hash', 64)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_seen_at']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
