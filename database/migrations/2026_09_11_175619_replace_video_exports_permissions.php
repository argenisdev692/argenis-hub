<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 001-video-edit, decision P2: the VIDEO_EXPORTS permissions were seeded
 * for a module that never shipped. Video Edits replaces them with VIDEO_EDITS
 * (seeded by RolePermissionSeeder); this removes the dead rows and their grants.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array OBSOLETE_PERMISSIONS = [
        'VIEW_ANY_VIDEO_EXPORTS',
        'CREATE_VIDEO_EXPORTS',
        'DOWNLOAD_VIDEO_EXPORTS',
    ];

    public function up(): void
    {
        $tables = (array) config('permission.table_names');
        $pivotKey = (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id');

        $ids = DB::table($tables['permissions'])
            ->whereIn('name', self::OBSOLETE_PERMISSIONS)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($tables, $pivotKey, $ids): void {
            DB::table($tables['role_has_permissions'])->whereIn($pivotKey, $ids)->delete();
            DB::table($tables['model_has_permissions'])->whereIn($pivotKey, $ids)->delete();
            DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Intentionally irreversible: the obsolete permissions guarded nothing, and
     * RolePermissionSeeder no longer defines them.
     */
    public function down(): void {}
};
