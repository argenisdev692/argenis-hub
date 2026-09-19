<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Studio-promotion lineage on `cvs`: which pipeline produced the row
 * (`source`), its language, and the parent/version UUIDs linking a promoted
 * Studio version back to the CV it was derived from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cvs', function (Blueprint $table): void {
            $table->string('source', 16)->default('upload'); // upload | studio | chat
            $table->string('language', 8)->nullable(); // en | es | pt-PT
            $table->string('parent_cv_uuid', 36)->nullable();
            $table->string('studio_version_uuid', 36)->nullable();

            $table->index('parent_cv_uuid', 'idx_cvs_parent_cv_uuid');
            $table->index('studio_version_uuid', 'idx_cvs_studio_version_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('cvs', function (Blueprint $table): void {
            $table->dropIndex('idx_cvs_parent_cv_uuid');
            $table->dropIndex('idx_cvs_studio_version_uuid');
            $table->dropColumn(['source', 'language', 'parent_cv_uuid', 'studio_version_uuid']);
        });
    }
};
