<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index for the roles/permissions list + export filter pattern
 * (BACKEND-PHP §4.1 #6 and §5.2): every query is scoped by soft-delete state and
 * then windowed on `created_at` (`date_from` / `date_to`). Without it the date
 * range is a sequential scan. `orderBy('name')` is already served by the existing
 * `unique(name, guard_name)` index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::table($tableNames['roles'], static function (Blueprint $table): void {
            $table->index(['deleted_at', 'created_at'], 'roles_deleted_at_created_at_index');
        });

        Schema::table($tableNames['permissions'], static function (Blueprint $table): void {
            $table->index(['deleted_at', 'created_at'], 'permissions_deleted_at_created_at_index');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], static function (Blueprint $table): void {
            $table->dropIndex('roles_deleted_at_created_at_index');
        });

        Schema::table($tableNames['permissions'], static function (Blueprint $table): void {
            $table->dropIndex('permissions_deleted_at_created_at_index');
        });
    }
};
