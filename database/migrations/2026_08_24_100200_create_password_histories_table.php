<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password reuse history (spec 001 FR-12 — the last 5 passwords may not be reused).
 *
 * Only Argon2id hashes are stored, never plaintext. Deliberately WITHOUT
 * SoftDeletes: pruned credential material must leave the database for good —
 * a `deleted_at` tombstone would keep old password hashes readable forever,
 * which is a security regression, not an audit feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
