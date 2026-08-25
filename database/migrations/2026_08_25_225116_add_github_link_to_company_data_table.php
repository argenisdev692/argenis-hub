<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GitHub was the one social channel missing from the original table, so the
     * landing footer, emails and PDF exports could never show it.
     */
    public function up(): void
    {
        Schema::table('company_data', function (Blueprint $table) {
            $table->string('github_link')->nullable()->after('facebook_link');
        });
    }

    public function down(): void
    {
        Schema::table('company_data', function (Blueprint $table) {
            $table->dropColumn('github_link');
        });
    }
};
