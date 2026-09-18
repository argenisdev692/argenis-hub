<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The student's practical exercise ("EJERCICIO PRÁCTICO") closing every script.
 * Nullable because versions written before it existed have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_script_versions', function (Blueprint $table): void {
            $table->json('practice_exercise')->nullable()->after('verification_checklist');
        });
    }

    public function down(): void
    {
        Schema::table('course_script_versions', function (Blueprint $table): void {
            $table->dropColumn('practice_exercise');
        });
    }
};
