<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A posting's identity is (owner, canonical URL), not the URL alone: with a
 * global unique index the second user to ingest a public job ad hit a 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_postings', function (Blueprint $table): void {
            $table->dropUnique(['url_hash']);
            $table->unique(['user_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('studio_postings', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'url_hash']);
            $table->unique(['url_hash']);
        });
    }
};
