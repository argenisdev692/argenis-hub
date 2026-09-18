<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Company website as delivered by the source payload (spec US-3:
        // resolution prefers the payload domain before paid search).
        Schema::table('scout_job_postings', function (Blueprint $table): void {
            $table->string('company_url', 2048)->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('scout_job_postings', function (Blueprint $table): void {
            $table->dropColumn('company_url');
        });
    }
};
