<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brute-force account lockout state (spec 001 FR-05).
 *
 * The rolling failure counter lives in the cache (store-agnostic), but the
 * resulting lock is persisted so it survives a cache flush, a deploy, or a
 * worker restart — and so it stays queryable for audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('locked_until')->nullable()->after('must_change_password');

            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['locked_until']);
            $table->dropColumn('locked_until');
        });
    }
};
