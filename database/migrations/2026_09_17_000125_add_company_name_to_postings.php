<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Publisher name as delivered (resolution input for T043; the
        // canonical identity stays `canonical_domain` on the company).
        Schema::table('scout_job_postings', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('scout_job_postings', function (Blueprint $table): void {
            $table->dropColumn('company_name');
        });
    }
};
