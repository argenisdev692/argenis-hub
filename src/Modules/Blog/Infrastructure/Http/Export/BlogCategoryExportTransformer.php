<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Http\Export;

use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;
use Shared\Domain\Ports\ExportPort;

/**
 * Maps a {@see BlogCategoryEloquentModel} row to export columns so CSV, Excel and
 * PDF stay consistent with the on-screen list. The module ships only this
 * transformer — the writer / streamer / PDF renderer live behind the Shared
 * {@see ExportPort} (BACKEND-PHP §8). The cover image is
 * intentionally omitted (a tabular/PDF report carries text, not binaries).
 */
final readonly class BlogCategoryExportTransformer
{
    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForTable(BlogCategoryEloquentModel $category): array
    {
        return [
            'Name' => $category->blog_category_name ?? '—',
            'Description' => $category->blog_category_description ?? '—',
            'Author' => self::authorLabel($category),
            'Created' => $category->created_at?->toDateTimeString() ?? '—',
            'Status' => $category->deleted_at !== null ? 'Suspended' : 'Active',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public static function transformForPdf(BlogCategoryEloquentModel $category): array
    {
        return self::transformForTable($category);
    }

    private static function authorLabel(BlogCategoryEloquentModel $category): string
    {
        $name = trim(sprintf('%s %s', $category->user?->first_name ?? '', $category->user?->last_name ?? ''));

        return $name !== '' ? $name : 'System';
    }
}
